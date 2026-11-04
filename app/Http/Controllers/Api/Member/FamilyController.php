<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{Family, Member, User, UserNotification};
use App\Support\{CustomForm, MemberApp};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app: Home, Family Details / View Member, Add / Update member, Remove / Migrate, Removed Members, Update Head, join requests, My Profile. */
class FamilyController extends Controller
{
    private function family(Request $r): Family { return Family::withoutGlobalScopes()->findOrFail($r->user()->family_id); }
    private function members(Request $r) { return Member::withoutGlobalScopes()->where('family_id', $r->user()->family_id); }
    private function isHead(Request $r): bool { return (bool) $this->members($r)->whereKey($r->user()->member_id)->value('is_head'); }
    private function authorizeFor(Request $r, Member $m): void
    {
        abort_unless($m->family_id === $r->user()->family_id, 404);
        abort_unless($this->isHead($r) || $m->id === $r->user()->member_id, 403, 'Only the Head of Family can do this.');
    }
    private function onlyHead(Request $r): void { abort_unless($this->isHead($r), 403, 'Only the Head of Family can do this.'); }

    public function home(Request $r)
    {
        $f = $this->family($r);
        $all = $this->members($r)->where('status', 'active')->where('approval', 'approved')->orderByDesc('is_head')->orderBy('id')->get();
        return [
            'family' => MemberApp::family($f), 'me' => MemberApp::member($all->firstWhere('id', $r->user()->member_id) ?? $all->first()),
            'head' => MemberApp::member($all->firstWhere('is_head', true) ?? $all->first()),
            'members' => $all->where('is_head', false)->values()->map(fn ($m) => MemberApp::member($m)),
            'pending_requests' => $this->isHead($r) ? $this->members($r)->where('approval', 'pending')->count() : 0,
            'unread_notifications' => UserNotification::where('user_id', $r->user()->id)->whereNull('read_at')->count(),
            'is_head' => $this->isHead($r),
        ];
    }

    public function show(Request $r, Member $member)
    {
        abort_unless($member->family_id === $r->user()->family_id, 404);
        $extra = $member->is_head ? (Family::withoutGlobalScopes()->whereKey($member->family_id)->first()?->extra ?? []) : ($member->extra ?? []);
        return ['member' => MemberApp::member($member) + ['extra' => $extra ?: (object) [], 'parinay' => \App\Models\MatrimonyProfile::where('member_id', $member->id)->first(['parinay_id', 'status'])]];
    }

    public function store(Request $r)
    {
        $this->onlyHead($r);
        $d = $r->validate(['relation' => 'required|string|max:40'] + MemberApp::rules() + CustomForm::rules('member'));
        $f = $this->family($r);
        $d = MemberApp::location($d + ['state' => $f->state, 'district' => $f->district]);
        $m = new Member;
        foreach (MemberApp::MEMBER_COLS as $c) if (array_key_exists($c, $d)) $m->{$c} = $d[$c];
        foreach (['address', 'city', 'pincode', 'resident_type'] as $c) $m->{$c} ??= $f->{$c};
        $m->family_id = $f->id; $m->source = 'app'; $m->approval = 'approved'; $m->status = 'active'; $m->is_head = false;
        $m->extra = CustomForm::collect($r, 'member');
        if ($r->hasFile('photo')) $m->photo = MemberApp::storePhoto($r->file('photo'), 'members');
        $m->save();
        $f->refreshCount();
        return response()->json(['member' => MemberApp::member($m), 'message' => 'Member added.'], 201);
    }

    public function update(Request $r, Member $member)
    {
        $this->authorizeFor($r, $member);
        $form = $member->is_head ? 'family' : 'member';
        $d = $r->validate(MemberApp::rules(false) + CustomForm::rules($form));
        $d = MemberApp::location($d);
        if ($member->is_head) {
            $f = $this->family($r);
            foreach (MemberApp::FAMILY_COLS as $c) if (array_key_exists($c, $d) && ! in_array($c, ['mobile'], true)) $f->{$c} = $d[$c];
            if (isset($d['name'])) $f->head_name = $d['name'];
            if ($r->hasFile('photo')) $f->photo = MemberApp::storePhoto($r->file('photo'), 'families');
            $f->extra = CustomForm::collect($r, 'family', $f->extra);
            $f->save();
            $f->syncHead();
            $member->refresh();
        } else {
            foreach (MemberApp::MEMBER_COLS as $c) if (array_key_exists($c, $d) && $c !== 'mobile') $member->{$c} = $d[$c];
            if ($r->hasFile('photo')) $member->photo = MemberApp::storePhoto($r->file('photo'), 'members');
            $member->extra = CustomForm::collect($r, 'member', $member->extra);
            $member->save();
        }
        return ['member' => MemberApp::member($member), 'message' => 'Profile updated.'];
    }

    /** "Remove / Migrate": archives the member (Died / Married / Migrated / Naya Chulha / Wrong Entry). */
    public function remove(Request $r, Member $member)
    {
        $this->onlyHead($r);
        abort_unless($member->family_id === $r->user()->family_id, 404);
        abort_if($member->is_head, 422, 'The Head of Family cannot be removed. Use "Update Head of Family" first.');
        $d = $r->validate(['archive_type' => 'required|in:' . implode(',', Member::ARCHIVE_TYPES), 'note' => 'nullable|string|max:250']);
        $member->update(['status' => 'archived', 'archive_type' => $d['archive_type'], 'archive_note' => $d['note'] ?? null, 'archived_at' => now()]);
        $this->family($r)->refreshCount();
        User::where('role', 'member')->where('member_id', $member->id)->update(['is_active' => false]);
        return ['message' => 'Member removed.'];
    }

    public function removed(Request $r)
    {
        $this->onlyHead($r);
        return ['data' => $this->members($r)->where('status', 'archived')->orderByDesc('archived_at')->get()->map(fn ($m) => MemberApp::member($m) + ['archived_at' => $m->archived_at?->toDateString(), 'archive_note' => $m->archive_note])];
    }

    public function restore(Request $r, Member $member)
    {
        $this->onlyHead($r);
        abort_unless($member->family_id === $r->user()->family_id && $member->status === 'archived', 404);
        abort_if($member->archive_type === 'Died', 422, 'This member cannot be restored.');
        $member->update(['status' => 'active', 'archive_type' => null, 'archive_note' => null, 'archived_at' => null]);
        $this->family($r)->refreshCount();
        User::where('role', 'member')->where('member_id', $member->id)->update(['is_active' => true]);
        return ['message' => 'Member added back.'];
    }

    /** "Update Head of Family": hand the head role to another active adult member of the family. */
    public function changeHead(Request $r)
    {
        $this->onlyHead($r);
        $d = $r->validate(['member_id' => 'required|integer']);
        $new = $this->members($r)->where('status', 'active')->where('approval', 'approved')->where('is_head', false)->findOrFail($d['member_id']);
        abort_unless($new->dob && $new->dob->age >= 18, 422, 'The Head must be at least 18 years old.');
        DB::transaction(function () use ($r, $new) {
            $f = $this->family($r);
            $old = $this->members($r)->where('is_head', true)->first();
            $old?->update(['is_head' => false, 'relation' => $old->relation === 'Self' ? 'OTHER' : $old->relation]);
            $new->update(['is_head' => true, 'relation' => 'Self']);
            $f->forceFill(['head_name' => $new->name, 'surname' => $new->surname ?: $f->surname, 'father_husband_name' => $new->father_husband_name ?: $f->father_husband_name,
                'gender' => $new->gender, 'dob' => $new->dob, 'blood_group' => $new->blood_group, 'education' => $new->education, 'occupation' => $new->occupation,
                'profession' => $new->profession, 'business_type' => $new->business_type, 'marital_status' => $new->marital_status, 'email' => $new->email,
                'mobile' => $new->mobile ?: $f->mobile, 'photo' => $new->photo ?: $f->photo])->save();
        });
        return ['message' => 'Head of Family changed.'];
    }

    // ---- join requests (a Member who registered with this Family ID) ----
    public function requests(Request $r)
    {
        $this->onlyHead($r);
        return ['data' => $this->members($r)->where('approval', 'pending')->orderBy('id')->get()->map(fn ($m) => MemberApp::member($m))];
    }

    public function decide(Request $r, Member $member, string $action)
    {
        $this->onlyHead($r);
        abort_unless($member->family_id === $r->user()->family_id && $member->approval === 'pending' && in_array($action, ['approve', 'reject'], true), 404);
        $member->update(['approval' => $action === 'approve' ? 'approved' : 'rejected']);
        if ($action === 'approve') $this->family($r)->refreshCount();
        if ($member->requested_by) UserNotification::create(['user_id' => $member->requested_by, 'title' => $action === 'approve' ? 'Your request has been approved' : 'Your request has been rejected']);
        return ['message' => $action === 'approve' ? 'Member approved.' : 'Request rejected.'];
    }

    /** Family level settings shown on the profile (mediclaim, Maheshwari Pakshik). */
    public function familySettings(Request $r)
    {
        $this->onlyHead($r);
        $d = $r->validate(['has_mediclaim' => 'sometimes|boolean', 'pakshik_member' => 'sometimes|boolean', 'income_range' => 'sometimes|nullable|string|max:80', 'resident_type' => 'sometimes|nullable|string|max:20']);
        $f = $this->family($r);
        $f->update($d);
        return ['family' => MemberApp::family($f)];
    }

    public function profile(Request $r)
    {
        $m = Member::withoutGlobalScopes()->findOrFail($r->user()->member_id);
        $f = $this->family($r);
        $extra = $m->is_head ? ($f->extra ?? []) : ($m->extra ?? []);
        return ['member' => MemberApp::member($m) + ['extra' => $extra ?: (object) []], 'family' => MemberApp::family($f), 'extra_fields' => CustomForm::fields($m->is_head ? 'family' : 'member')];
    }

    public function updateProfile(Request $r)
    {
        return $this->update($r, Member::withoutGlobalScopes()->findOrFail($r->user()->member_id));
    }
}
