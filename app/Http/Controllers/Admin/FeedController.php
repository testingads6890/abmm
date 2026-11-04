<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedPost;
use App\Models\User;
use App\Services\GreetingService;
use Illuminate\Http\Request;
use App\Models\Lookup;
use Illuminate\Support\Facades\Storage;

/** Moderation of member achievements + (Feature 4) news. Area-scoped by HasArea: State Sub-Admin sees only own pradesh, District Operator own district. */
class FeedController extends Controller
{
    public function index(Request $r)
    {
        $status = array_key_exists($r->query('status'), FeedPost::STATUSES) ? $r->query('status') : 'pending';
        $type = array_key_exists($r->query('type'), FeedPost::TYPES) ? $r->query('type') : null;
        $q = FeedPost::with('member:id,name,surname,mobile')->where('status', $status)->when($type, fn ($x) => $x->where('type', $type))
            ->when($r->query('q'), fn ($x, $s) => $x->where('title', 'like', '%' . $s . '%'));
        return view('admin.feed.index', [
            'rows' => $q->orderByDesc('id')->paginate(25)->withQueryString(), 'status' => $status, 'type' => $type,
            'counts' => FeedPost::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(int $id)
    {
        return view('admin.feed.show', ['p' => FeedPost::with(['member', 'submitter'])->findOrFail($id)]);
    }

    /** Approve and publish; congratulation goes to the achiever, plus an optional push to district / state / everyone. */
    public function approve(Request $r, int $id)
    {
        $p = FeedPost::findOrFail($id);
        $d = $r->validate(['audience' => 'required|in:none,district,state,all', 'verified' => 'nullable|boolean']);
        $p->update(['status' => 'approved', 'review_note' => null, 'publish_at' => $p->publish_at ?? now(), 'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
            'proof_verified' => $p->proof ? $r->boolean('verified') : false]);

        $svc = app(GreetingService::class);
        $name = trim(($p->member?->name ?? '') . ' ' . ($p->member?->surname ?? ''));
        foreach (array_unique(array_filter([$p->member_id ? User::where('member_id', $p->member_id)->value('id') : null, $p->user_id])) as $uid) {
            if ($u = User::find($uid)) $svc->notify($u, 'Congratulations!', 'Your achievement "' . $p->title . '" has been published in the community.', ['type' => 'feed', 'id' => $p->id]);
        }
        $msg = 'Approved.';
        if ($d['audience'] !== 'none') {
            $sent = $p->pushToAudience($d['audience'], 'Community pride stories', ($name ? "$name: " : '') . $p->title);
            $msg .= $sent === true ? ' Push bhej diya.' : ($sent === false ? ' (Push could not be sent.)' : ' (Push not sent: FCM or audience is not set.)');
        }
        return redirect()->route('admin.feed.index', ['status' => 'pending'])->with('ok', $msg);
    }

    /** "Changes needed" (member can fix and resubmit) or "Reject". */
    public function decide(Request $r, int $id, string $action)
    {
        $d = $r->validate(['note' => 'required|string|max:300']);
        $p = FeedPost::findOrFail($id);
        $p->update(['status' => $action === 'reject' ? 'rejected' : 'changes', 'review_note' => $d['note'], 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        if ($u = $p->user_id ? User::find($p->user_id) : null) {
            app(GreetingService::class)->notify($u, $action === 'reject' ? 'Achievement was not approved' : 'Achievement needs changes', $p->title . ' — ' . $d['note'], ['type' => 'feed', 'id' => $p->id]);
        }
        return redirect()->route('admin.feed.index', ['status' => 'pending'])->with('ok', $action === 'reject' ? 'Rejected.' : 'Sent back to the member for changes.');
    }


    // ---------- Admin-written news / condolence notices ----------

    public function create(Request $r)
    {
        $type = in_array($r->query('type'), ['news', 'condolence']) ? $r->query('type') : 'news';
        return view('admin.feed.form', ['p' => new FeedPost(['type' => $type]), 'pradeshList' => $this->pradeshList()]);
    }

    public function edit(int $id)
    {
        $p = FeedPost::findOrFail($id);
        abort_if($p->type === 'achievement', 404);
        return view('admin.feed.form', ['p' => $p, 'pradeshList' => $this->pradeshList()]);
    }

    public function store(Request $r)
    {
        $d = $this->newsData($r);
        $p = new FeedPost($d + ['status' => 'approved', 'user_id' => auth()->id(), 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        if (! auth()->user()->isSuper()) $p->pradesh = auth()->user()->pradesh;   // HasArea does this too; explicit so it never depends on the trait
        $this->saveImage($r, $p);
        $p->publish_at = $p->publish_at ?? now();
        $p->save();
        $msg = 'Published.';
        if (($d['notify_audience'] ?? 'none') !== 'none' && $p->publish_at <= now()) {
            $sent = $p->pushToAudience($p->notify_audience, $p->type === 'condolence' ? 'Shok Samachar' : 'Mahasabha Suchna', $p->title);
            if ($sent !== null) $p->update(['notified_at' => now()]);
            $msg .= $sent === true ? ' Push bhej diya.' : ($sent === false ? ' (Push could not be sent.)' : ' (Push not sent: FCM or audience is not set.)');
        } elseif (($d['notify_audience'] ?? 'none') !== 'none') $msg .= ' The push will go out automatically at publish time.';
        return redirect()->route('admin.feed.index', ['status' => 'approved', 'type' => $p->type])->with('ok', $msg);
    }

    public function update(Request $r, int $id)
    {
        $p = FeedPost::findOrFail($id);
        abort_if($p->type === 'achievement', 404);
        $d = $this->newsData($r, $p->type);
        $p->fill(collect($d)->except(['type', 'pradesh'])->all());
        if (auth()->user()->isSuper()) $p->pradesh = $d['pradesh'] ?? null;
        if ($r->hasFile('image')) { if ($p->image) Storage::disk('public')->delete($p->image); $this->saveImage($r, $p); }
        if ($r->boolean('remove_image') && $p->image && ! $r->hasFile('image')) { Storage::disk('public')->delete($p->image); $p->image = null; }
        $p->save();
        return redirect()->route('admin.feed.edit', $p->id)->with('ok', 'Updated.');
    }

    /** Toppers wall: approved ranks / exam selections of a year (print or "Save as PDF" from the browser to share). */
    public function toppers(Request $r)
    {
        $year = (int) ($r->query('year') ?: now()->year);
        $rows = FeedPost::with('member:id,name,surname,photo')->live()->where('type', 'achievement')->whereIn('category', FeedPost::TOPPER_CATEGORIES)
            ->whereRaw('YEAR(COALESCE(publish_at, created_at)) = ?', [$year])->orderBy('category')->orderBy('title')->get()->groupBy('category');
        return view('admin.feed.toppers', ['groups' => $rows, 'year' => $year]);
    }

    private function pradeshList()
    {
        return auth()->user()->isSuper() ? Lookup::where('type', 'pradesh')->orderBy('name')->pluck('name') : collect();
    }

    private function newsData(Request $r, ?string $fixedType = null): array
    {
        $d = $r->validate([
            'type' => $fixedType ? 'nullable' : 'required|in:news,condolence', 'title' => 'required|string|max:200', 'body' => 'nullable|string|max:3000',
            'image' => 'nullable|image|max:3072', 'pradesh' => 'nullable|string|max:200', 'district' => 'nullable|string|max:100',
            'publish_at' => 'nullable|date', 'expires_at' => 'nullable|date|after:publish_at', 'notify_audience' => 'required|in:none,district,state,all',
        ]);
        $d['pinned'] = $r->boolean('pinned');
        if ($fixedType) $d['type'] = $fixedType;
        unset($d['image']);
        return $d;
    }

    private function saveImage(Request $r, FeedPost $p): void
    {
        if ($r->hasFile('image')) $p->image = Storage::disk('public')->putFile('feed', $r->file('image'));
    }

    public function flag(Request $r, int $id, string $field)
    {
        $p = FeedPost::findOrFail($id);
        abort_unless(in_array($field, ['pinned', 'proof_verified']), 404);
        $p->update([$field => ! $p->{$field}]);
        return back()->with('ok', 'Updated.');
    }

    /** Private proof (marksheet / certificate) - only logged-in admins who can see the post. */
    public function proof(int $id)
    {
        $p = FeedPost::findOrFail($id);
        abort_unless($p->proof && Storage::disk('local')->exists($p->proof), 404);
        return Storage::disk('local')->response($p->proof);
    }

    public function destroy(int $id)
    {
        $p = FeedPost::findOrFail($id);
        foreach ([[$p->image, 'public'], [$p->proof, 'local']] as [$f, $disk]) if ($f) Storage::disk($disk)->delete($f);
        $p->delete();
        return redirect()->route('admin.feed.index')->with('ok', 'Hata diya.');
    }
}
