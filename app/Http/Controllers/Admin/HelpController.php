<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpRequest;
use App\Models\User;
use App\Services\GreetingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Help & support queue. Area-scoped by HasArea: State Sub-Admin sees only own pradesh, District Operator own district. */
class HelpController extends Controller
{
    public function index(Request $r)
    {
        $status = array_key_exists($r->query('status'), HelpRequest::STATUSES) ? $r->query('status') : 'submitted';
        $type = array_key_exists($r->query('type'), HelpRequest::TYPES) ? $r->query('type') : null;
        $q = HelpRequest::with('member:id,name,surname,mobile')->where('status', $status)->when($type, fn ($x) => $x->where('type', $type))
            ->when($r->query('q'), fn ($x, $s) => $x->where('title', 'like', '%' . $s . '%'));
        return view('admin.help.index', [
            'rows' => $q->orderByDesc('id')->paginate(25)->withQueryString(), 'status' => $status, 'type' => $type,
            'counts' => HelpRequest::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(int $id)
    {
        return view('admin.help.show', ['h' => HelpRequest::with(['member', 'user'])->findOrFail($id)]);
    }

    public function decide(Request $r, int $id)
    {
        $d = $r->validate(['status' => 'required|in:' . implode(',', array_keys(HelpRequest::STATUSES)), 'note' => 'nullable|required_if:status,rejected,forwarded|string|max:500']);
        $h = HelpRequest::findOrFail($id);
        $h->update(['status' => $d['status'], 'committee_note' => $d['note'] ?? null, 'decided_by' => auth()->id(), 'decided_at' => now()]);
        if ($u = User::find($h->user_id)) {
            app(GreetingService::class)->notify($u, 'Your help request: ' . (HelpRequest::STATUSES[$d['status']] ?? $d['status']), $h->title . (filled($d['note'] ?? null) ? ' — ' . $d['note'] : ''), ['type' => 'help', 'id' => $h->id]);
        }
        return back()->with('ok', 'Status updated, the member has been notified.');
    }

    /** Private document - only admins who can see the request. */
    public function doc(int $id, int $n)
    {
        $h = HelpRequest::findOrFail($id);
        $path = ($h->documents ?? [])[$n] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path);
    }

    public function destroy(int $id)
    {
        $h = HelpRequest::findOrFail($id);
        foreach ($h->documents ?? [] as $f) Storage::disk('local')->delete($f);
        $h->delete();
        return redirect()->route('admin.help.index')->with('ok', 'Hata diya.');
    }
}
