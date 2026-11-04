<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\HelpRequest;
use App\Models\Member;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Member app: raise a help / support request with documents and follow its status. */
class HelpController extends Controller
{
    private function item(HelpRequest $h, bool $full = false): array
    {
        return [
            'id' => $h->id, 'type' => $h->type, 'type_label' => HelpRequest::TYPES[$h->type] ?? $h->type, 'title' => $h->title, 'status' => $h->status,
            'status_label' => HelpRequest::STATUSES[$h->status] ?? $h->status, 'amount' => $h->amount, 'committee_note' => $h->committee_note,
            'created_at' => $h->created_at?->toIso8601String(), 'decided_at' => $h->decided_at?->toIso8601String(),
        ] + ($full ? ['details' => $h->details, 'documents_count' => count($h->documents ?? [])] : []);
    }

    public function types()
    {
        return ['types' => collect(HelpRequest::TYPES)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values()];
    }

    /** Requests of the whole family (the Head sees every member's; a member sees his / her own). */
    public function index(Request $r)
    {
        $u = $r->user();
        $isHead = (bool) Member::withoutGlobalScopes()->whereKey($u->member_id)->value('is_head');
        $q = HelpRequest::withoutGlobalScopes()->where('family_id', $u->family_id)->when(! $isHead, fn ($x) => $x->where('user_id', $u->id));
        return ['data' => $q->latest()->limit(50)->get()->map(fn ($h) => $this->item($h))->values()];
    }

    public function show(Request $r, int $id)
    {
        return ['data' => $this->item($this->mine($r, $id), true)];
    }

    /** POST help — multipart: type, title, details, amount?, member_id?, documents[] (max 3 images / PDFs). */
    public function store(Request $r)
    {
        $d = $r->validate([
            'type' => 'required|in:' . implode(',', array_keys(HelpRequest::TYPES)), 'title' => 'required|string|max:200', 'details' => 'required|string|max:3000',
            'amount' => 'nullable|numeric|min:0|max:99999999', 'member_id' => 'nullable|integer',
            'documents' => 'nullable|array|max:3', 'documents.*' => 'file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);
        $u = $r->user();
        $fam = Family::withoutGlobalScopes()->findOrFail($u->family_id);
        $member = Member::withoutGlobalScopes()->where('family_id', $fam->id)->whereKey($d['member_id'] ?? $u->member_id)->firstOrFail();
        $docs = collect($r->file('documents', []))->map(fn ($f) => Storage::disk('local')->putFile('help', $f))->all();

        $h = HelpRequest::withoutGlobalScopes()->create([
            'user_id' => $u->id, 'family_id' => $fam->id, 'member_id' => $member->id, 'pradesh' => $fam->pradesh, 'state' => $fam->state, 'district' => $fam->district,
            'type' => $d['type'], 'title' => $d['title'], 'details' => $d['details'], 'amount' => $d['amount'] ?? null, 'documents' => $docs ?: null,
        ]);
        UserNotification::notifyAdmins('New help request: ' . $h->title, route('admin.help.show', $h->id), $fam->pradesh);
        return response()->json(['ok' => true, 'id' => $h->id, 'message' => 'Your request has been sent to the committee.'], 201);
    }

    private function mine(Request $r, int $id): HelpRequest
    {
        $u = $r->user();
        $isHead = (bool) Member::withoutGlobalScopes()->whereKey($u->member_id)->value('is_head');
        return HelpRequest::withoutGlobalScopes()->where('family_id', $u->family_id)->when(! $isHead, fn ($x) => $x->where('user_id', $u->id))->findOrFail($id);
    }
}
