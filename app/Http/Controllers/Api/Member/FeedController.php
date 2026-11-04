<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\FeedPost;
use App\Models\Member;
use App\Models\UserNotification;
use App\Support\MemberApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Member app: community feed (approved achievements + news), submit an achievement, my posts, like. */
class FeedController extends Controller
{
    private function item(FeedPost $p, array $liked = [], bool $mine = false): array
    {
        $m = $p->member;
        return [
            'id' => $p->id, 'type' => $p->type, 'category' => $p->category, 'category_label' => FeedPost::CATEGORIES[$p->category] ?? null,
            'title' => $p->title, 'body' => $p->body, 'image_url' => MemberApp::photoUrl($p->image), 'verified' => (bool) $p->proof_verified, 'pinned' => (bool) $p->pinned,
            'name' => $p->type === 'achievement' && $m ? trim($m->name . ' ' . $m->surname) : null, 'photo_url' => $m ? MemberApp::photoUrl($m->photo) : null,
            'place' => $p->district ?: $p->state, 'likes_count' => $p->likes_count, 'liked' => in_array($p->id, $liked),
            'posted_at' => ($p->publish_at ?? $p->created_at)?->toIso8601String(),
        ] + ($mine ? ['status' => $p->status, 'review_note' => $p->review_note] : []);
    }

    /** GET feed?type=achievement|news|condolence&category=&scope=state|district|all&page= */
    public function index(Request $r)
    {
        $d = $r->validate(['type' => 'nullable|in:achievement,news,condolence', 'category' => 'nullable|string|max:30', 'scope' => 'nullable|in:district,state,all']);
        $u = $r->user();
        $fam = Family::withoutGlobalScopes()->findOrFail($u->family_id);
        $q = FeedPost::withoutGlobalScopes()->live()->with('member:id,name,surname,photo');
        if (! empty($d['type'])) $q->where('type', $d['type']);
        if (! empty($d['category'])) $q->where('category', $d['category']);
        $scope = $d['scope'] ?? 'all';
        if ($scope === 'state') $q->where(fn ($w) => $w->whereNull('pradesh')->when($fam->pradesh, fn ($x) => $x->orWhere('pradesh', $fam->pradesh)));
        if ($scope === 'district') $q->where(fn ($w) => $w->whereNull('district')->when($fam->district, fn ($x) => $x->orWhere('district', $fam->district)));

        $page = $q->orderByDesc('pinned')->orderByDesc(DB::raw('coalesce(publish_at, created_at)'))->simplePaginate(15);
        $liked = DB::table('feed_likes')->where('user_id', $u->id)->whereIn('post_id', $page->pluck('id'))->pluck('post_id')->all();
        return ['data' => $page->getCollection()->map(fn ($p) => $this->item($p, $liked))->values(), 'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null];
    }

    public function mine(Request $r)
    {
        $rows = FeedPost::withoutGlobalScopes()->with('member:id,name,surname,photo')->where('user_id', $r->user()->id)->where('type', 'achievement')->latest()->limit(50)->get();
        return ['data' => $rows->map(fn ($p) => $this->item($p, [], true))->values()];
    }

    /** GET feed/toppers?year= — approved ranks / exam selections of the year, grouped by category. */
    public function toppers(Request $r)
    {
        $year = (int) ($r->query('year') ?: now()->year);
        $base = FeedPost::withoutGlobalScopes()->live()->where('type', 'achievement')->whereIn('category', FeedPost::TOPPER_CATEGORIES);
        $years = (clone $base)->selectRaw('YEAR(COALESCE(publish_at, created_at)) y')->distinct()->orderByDesc('y')->pluck('y')->all();
        $rows = (clone $base)->with('member:id,name,surname,photo')->whereRaw('YEAR(COALESCE(publish_at, created_at)) = ?', [$year])->orderBy('title')->limit(300)->get();
        $groups = $rows->groupBy('category')->map(fn ($items, $cat) => [
            'category' => $cat, 'label' => FeedPost::CATEGORIES[$cat] ?? $cat, 'items' => $items->map(fn ($p) => $this->item($p))->values(),
        ])->values();
        return ['year' => $year, 'years' => $years ?: [(int) now()->year], 'groups' => $groups];
    }

    public function categories() { return ['data' => collect(FeedPost::CATEGORIES)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values()]; }

    /** POST feed — multipart: category, title, body?, member_id?, image?, proof? */
    public function store(Request $r)
    {
        $d = $this->validated($r);
        $u = $r->user();
        $member = Member::withoutGlobalScopes()->where('family_id', $u->family_id)->whereKey($d['member_id'] ?? $u->member_id)->firstOrFail();
        $fam = Family::withoutGlobalScopes()->findOrFail($u->family_id);

        $p = FeedPost::withoutGlobalScopes()->create([
            'type' => 'achievement', 'category' => $d['category'], 'title' => $d['title'], 'body' => $d['body'] ?? null,
            'image' => $r->hasFile('image') ? Storage::disk('public')->putFile('feed', $r->file('image')) : null,
            'proof' => $r->hasFile('proof') ? Storage::disk('local')->putFile('proofs', $r->file('proof')) : null,
            'user_id' => $u->id, 'family_id' => $fam->id, 'member_id' => $member->id,
            'pradesh' => $fam->pradesh, 'state' => $fam->state, 'district' => $fam->district, 'status' => 'pending',
        ]);
        UserNotification::notifyAdmins('New achievement for review: ' . $p->title, route('admin.feed.show', $p->id), $fam->pradesh);
        return response()->json(['ok' => true, 'id' => $p->id, 'message' => 'Your achievement has been sent for review.'], 201);
    }

    /** POST feed/{id} — resubmit after "changes needed" / rejection (or edit while pending). */
    public function update(Request $r, int $id)
    {
        $p = $this->own($r, $id);
        abort_unless(in_array($p->status, ['pending', 'changes', 'rejected']), 422, 'An approved post cannot be edited.');
        $d = $this->validated($r);
        $p->fill(['category' => $d['category'], 'title' => $d['title'], 'body' => $d['body'] ?? null, 'status' => 'pending', 'review_note' => null]);
        if ($r->hasFile('image')) { if ($p->image) Storage::disk('public')->delete($p->image); $p->image = Storage::disk('public')->putFile('feed', $r->file('image')); }
        if ($r->hasFile('proof')) { if ($p->proof) Storage::disk('local')->delete($p->proof); $p->proof = Storage::disk('local')->putFile('proofs', $r->file('proof')); $p->proof_verified = false; }
        $p->save();
        UserNotification::notifyAdmins('Achievement for re-review: ' . $p->title, route('admin.feed.show', $p->id), $p->pradesh);
        return ['ok' => true, 'message' => 'Sent for re-review.'];
    }

    public function destroy(Request $r, int $id)
    {
        $p = $this->own($r, $id);
        abort_unless($p->status !== 'approved', 422, 'Contact the admin to remove an approved post.');
        foreach ([[$p->image, 'public'], [$p->proof, 'local']] as [$f, $disk]) if ($f) Storage::disk($disk)->delete($f);
        $p->delete();
        return response()->noContent();
    }

    /** POST feed/{id}/like — toggles. */
    public function like(Request $r, int $id)
    {
        $p = FeedPost::withoutGlobalScopes()->live()->findOrFail($id);
        $uid = $r->user()->id;
        $liked = DB::transaction(function () use ($p, $uid) {
            if (DB::table('feed_likes')->where(['post_id' => $p->id, 'user_id' => $uid])->delete()) { $p->decrement('likes_count'); return false; }
            if (DB::table('feed_likes')->insertOrIgnore(['post_id' => $p->id, 'user_id' => $uid, 'created_at' => now()])) $p->increment('likes_count');
            return true;
        });
        return ['liked' => $liked, 'likes_count' => max(0, (int) $p->fresh()->likes_count)];
    }

    private function own(Request $r, int $id): FeedPost
    {
        return FeedPost::withoutGlobalScopes()->where('user_id', $r->user()->id)->where('type', 'achievement')->findOrFail($id);
    }

    private function validated(Request $r): array
    {
        return $r->validate([
            'category' => 'required|in:' . implode(',', array_keys(FeedPost::CATEGORIES)), 'title' => 'required|string|max:200', 'body' => 'nullable|string|max:1500',
            'member_id' => 'nullable|integer', 'image' => 'nullable|image|max:3072', 'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);
    }
}
