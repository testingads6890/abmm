<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Family, Lookup, Member};
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    /** Family-level filters (exact match) that the search accepts. */
    private const FAMILY_FILTERS = ['state', 'district', 'tehsil', 'pradesh', 'caste', 'gotra', 'income_range', 'resident_type', 'status'];
    /** Person-level filters: a family matches when the family or one of its active members has the value. */
    private const PERSON_FILTERS = ['gender', 'blood_group', 'occupation', 'profession', 'education', 'business_type', 'marital_status', 'gotra'];

    /** Cursor pagination (no OFFSET, no COUNT) so it stays fast at 10 lakh+ rows. Needs a search term or a filter, like the website. */
    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:60', 'per_page' => 'nullable|integer|min:10|max:50', 'letter' => 'nullable|alpha|size:1', 'anchal' => 'nullable|string|max:60']);
        $q = trim((string) $r->q);
        $filters = array_filter($r->only(array_merge(self::FAMILY_FILTERS, self::PERSON_FILTERS, ['surname'])), fn ($v) => $v !== null && $v !== '');
        if (mb_strlen($q) < 3 && ! $filters && ! $r->filled('anchal') && ! $r->filled('letter')) {
            return ['data' => [], 'next_cursor' => null, 'hint' => 'Search at least 3 characters or choose a filter.'];
        }

        $query = Family::query()->search($q ?: null)
            ->when(! $r->filled('status'), fn ($x) => $x->where('status', 'active'));
        foreach (array_intersect_key($filters, array_flip(self::FAMILY_FILTERS)) as $k => $v) $query->where($k, $v);
        if ($r->filled('surname')) $query->where('surname', $r->surname);
        foreach (array_intersect_key($filters, array_flip(self::PERSON_FILTERS)) as $k => $v) {
            $val = $k === 'gender' ? ucfirst(strtolower($v)) : $v;
            $query->where(fn ($w) => $w->where($k, $val)->orWhereHas('members', fn ($m) => $m->withoutGlobalScopes()->where('status', 'active')->where($k, $val)));
        }
        if ($r->filled('anchal')) {
            $list = Lookup::pradeshesOf($r->anchal);
            $list ? $query->whereIn('pradesh', $list) : $query->whereRaw('1 = 0');
        }
        if ($r->filled('letter')) $query->where('surname', 'like', strtoupper($r->letter) . '%');

        $page = $query->orderBy('id')
            ->select(['id', 'family_code', 'surname', 'head_name', 'state', 'district', 'tehsil', 'pradesh', 'mobile', 'member_count', 'status'])
            ->cursorPaginate($r->integer('per_page', 30));
        return ['data' => $page->items(), 'next_cursor' => $page->nextCursor()?->encode()];
    }

    public function show(Family $family)
    {
        return ['family' => $family->only(['id', 'family_code', 'surname', 'head_name', 'father_husband_name', 'gender', 'dob', 'mobile', 'email', 'address', 'pincode', 'pradesh', 'state', 'district', 'tehsil', 'member_count', 'status'])
            + ['anchal' => Lookup::anchalOf($family->pradesh), 'aadhar_masked' => $family->aadhar_masked],
            'members' => $this->memberRows($family)];
    }

    /** Every member of the family, with the head first, including archived ones (so their status is visible). */
    public function members(Family $family)
    {
        return ['members' => $this->memberRows($family)];
    }

    /** Hanging Data: move a member out of the family list (Died / Married / Migrated / Naya Chulha / Wrong Entry). */
    public function archiveMember(Request $r, Family $family, Member $member)
    {
        abort_unless($member->family_id === $family->id && ! $member->is_head, 404);
        $d = $r->validate(['archive_type' => 'required|in:' . implode(',', Member::ARCHIVE_TYPES), 'archive_note' => 'nullable|max:200']);
        $member->update($d + ['status' => 'archived', 'archived_at' => now()]);
        $family->refreshCount();
        return ['ok' => true, 'message' => 'Member moved out of the family list.', 'member_id' => $member->id];
    }

    /** Super Admin and State Sub-Admin only: move a member (not the head) into another family, as an active member. */
    public function migrateMember(Request $r, Family $family, Member $member)
    {
        abort_unless($r->user()->isSuper() || $r->user()->isStateAdmin(), 403);
        abort_unless($member->family_id === $family->id && ! $member->is_head, 404);
        $d = $r->validate(['target_family_code' => 'required|string|max:20']);
        // Family is area-scoped, so a sub-admin can only migrate into a family in their own area.
        $target = Family::where('family_code', trim($d['target_family_code']))->where('id', '!=', $family->id)->first();
        if (! $target) return response()->json(['message' => 'Family ID not found in your area.', 'errors' => ['target_family_code' => ['Family ID not found in your area.']]], 422);
        $member->update(['family_id' => $target->id, 'status' => 'active', 'archive_type' => null, 'archived_at' => null, 'archive_note' => null]);
        $family->refreshCount();
        $target->refreshCount();
        return ['ok' => true, 'message' => "Member migrated to {$target->family_code}.", 'family_code' => $target->family_code];
    }

    private function memberRows(Family $family): \Illuminate\Support\Collection
    {
        return $family->members()->withoutGlobalScopes()->orderByDesc('is_head')->get(['id', 'member_code', 'name', 'surname', 'relation', 'gender', 'dob', 'mobile', 'marital_status', 'status', 'is_head', 'archive_type']);
    }
}
