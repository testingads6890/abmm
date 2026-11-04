<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{Business, Family, UserNotification};
use App\Services\{AdService, GeoTarget, PointsService};
use App\Services\Payments\PaymentLedger;
use App\Support\MemberApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app: business categories & listings, organisations, paid ads, B2B marketplace (products, quotations, leads), business groups, meetings, referrals. */
class BizController extends Controller
{
    public function __construct(private AdService $ads, private PaymentLedger $ledger) {}

    private function family(Request $r): Family { return Family::withoutGlobalScopes()->findOrFail($r->user()->family_id); }

    private function own(Request $r, int $id): Business { return Business::withoutGlobalScopes()->where('family_id', $r->user()->family_id)->findOrFail($id); }

    // ---------------- categories & listings ----------------
    public function categories()
    {
        $all = DB::table('business_categories')->where('is_active', true)->orderBy('sort')->orderBy('name')->get();
        $build = function ($pid) use (&$build, $all) { return $all->where('parent_id', $pid)->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'children' => $build($c->id)])->values()->all(); };
        return $build(null);
    }

    private function descendants(int $id): array
    {
        $ids = [$id]; $queue = [$id];
        while ($queue) { $kids = DB::table('business_categories')->whereIn('parent_id', $queue)->pluck('id')->all(); $queue = array_diff($kids, $ids); $ids = array_merge($ids, $queue); }
        return $ids;
    }

    /** Listing visibility: free until the community configures paid business plans; then only businesses with a live listing show. */
    private function visible()
    {
        $q = Business::withoutGlobalScopes()->where('status', 'active');
        if (config('abmm.listing_requires_subscription') && DB::table('plans')->where('audience', 'business')->where('is_active', true)->exists())
            $q->whereDate('listing_until', '>=', today());
        return $q;
    }

    public function businesses(Request $r)
    {
        $f = $this->family($r);
        $q = $this->visible()->when($r->query('q'), fn ($w, $t) => $w->where(fn ($x) => $x->where('name', 'like', "%$t%")->orWhere('type', 'like', "%$t%")->orWhere('description', 'like', "%$t%")))
            ->when($r->query('kind'), fn ($w, $v) => $w->where('org_kind', $v))->when($r->query('country'), fn ($w, $v) => $w->where('country', $v))->when($r->query('city'), fn ($w, $v) => $w->where('city', $v))
            ->when($r->query('category_id'), fn ($w, $v) => $w->whereIn('category_id', $this->descendants((int) $v)))->when($r->boolean('nonprofit'), fn ($w) => $w->where('org_kind', 'nonprofit'));
        // category-top ads float to the top of a category page
        $top = $r->query('category_id') ? $this->ads->serve($f, 'category_top', (int) $r->query('category_id'), 2) : [];
        $page = $q->orderByDesc('is_featured')->orderByDesc('id')->cursorPaginate(30, ['id', 'name', 'type', 'address', 'mobile', 'email', 'image', 'description', 'org_kind', 'category_id', 'city', 'country', 'reg_no', 'has_12a', 'has_80g', 'seller_verified', 'is_featured']);
        return ['top_ads' => $top, 'data' => collect($page->items())->map(fn ($b) => $b->toArray() + ['image_url' => MemberApp::photoUrl($b->image)]), 'next_cursor' => $page->nextCursor()?->encode()];
    }

    public function business(Request $r, int $id)
    {
        $b = $this->visible()->findOrFail($id);
        DB::table('businesses')->where('id', $id)->increment('views');
        return $b->only(['id', 'name', 'type', 'address', 'mobile', 'email', 'description', 'org_kind', 'category_id', 'city', 'country', 'zone', 'reg_no', 'has_12a', 'has_80g', 'seller_verified', 'is_featured'])
            + ['image_url' => MemberApp::photoUrl($b->image), 'rating' => round((float) DB::table('business_reviews')->where('business_id', $id)->where('status', 'approved')->avg('rating'), 2),
               'products' => DB::table('products')->where('business_id', $id)->where('status', 'active')->count()];
    }

    public function myBusinesses(Request $r)
    {
        return Business::withoutGlobalScopes()->where('family_id', $r->user()->family_id)->orderByDesc('id')->get()->map(fn ($b) => $b->toArray() + ['image_url' => MemberApp::photoUrl($b->image)]);
    }

    /** Register or update a business / profit / non-profit organisation page. Needs admin approval before it is public. */
    public function saveBusiness(Request $r, ?int $id = null)
    {
        $d = $r->validate(['name' => 'required|string|max:150', 'type' => 'required|string|max:120', 'address' => 'required|string|max:400', 'mobile' => ['required', 'regex:/^[0-9+]{8,15}$/'],
            'email' => 'nullable|email|max:120', 'description' => 'nullable|string|max:1000', 'image' => 'nullable|image|max:3072', 'category_id' => 'nullable|exists:business_categories,id',
            'org_kind' => 'required|in:business,profit,nonprofit', 'reg_no' => 'required_if:org_kind,nonprofit|nullable|string|max:60', 'has_12a' => 'sometimes|boolean', 'has_80g' => 'sometimes|boolean',
            'city' => 'nullable|string|max:150', 'zone' => 'nullable|string|max:100']);
        $f = $this->family($r);
        if ($d['org_kind'] !== 'nonprofit') { $d['has_12a'] = false; $d['has_80g'] = false; }
        if ($r->hasFile('image')) $d['image'] = MemberApp::storePhoto($r->file('image'), 'business');
        $d = array_merge($d, ['country' => $f->country, 'city' => $d['city'] ?? $f->city, 'pradesh' => $f->pradesh, 'district' => $f->district]);
        if ($id) { $b = $this->own($r, $id); $b->update($d + ['status' => 'pending']); return ['id' => $b->id, 'message' => 'Update submitted, it will appear after approval.']; }
        $b = Business::withoutGlobalScopes()->create($d + ['family_id' => $f->id, 'status' => 'pending']);
        return response()->json(['id' => $b->id, 'message' => 'Submitted. It will appear after approval.'], 201);
    }

    // ---------------- ads ----------------
    public function adPackages(Request $r)
    {
        return DB::table('ad_packages')->where('is_active', true)->orderBy('sort')->get();
    }

    public function buyAd(Request $r)
    {
        $d = $r->validate(['business_id' => 'required|integer', 'package_id' => 'required|exists:ad_packages,id', 'title' => 'required|string|max:150', 'starts_on' => 'nullable|date|after_or_equal:today',
            'banner' => 'nullable|image|max:3072', 'link_url' => 'nullable|url|max:250', 'push_text' => 'nullable|string|max:200', 'category_id' => 'nullable|exists:business_categories,id', 'coupon' => 'nullable|string|max:30',
            'target' => 'nullable|array']);
        $b = $this->own($r, $d['business_id']);
        abort_unless($b->status === 'active', 422, 'The business must be approved first.');
        $pkg = DB::table('ad_packages')->where('is_active', true)->find($d['package_id']) ?? abort(404);
        if ($r->hasFile('banner')) $d['banner'] = MemberApp::storePhoto($r->file('banner'), 'ads');
        [$ad, $p] = $this->ads->purchase($r->user(), $b, $pkg, $d);
        return ['ad_id' => $ad->id, 'payment_no' => $p->number, 'checkout' => $this->ledger->checkout($p, ['title' => $pkg->name])];
    }

    public function myAds(Request $r)
    {
        return DB::table('business_ads as a')->join('businesses as b', 'b.id', '=', 'a.business_id')->where('b.family_id', $r->user()->family_id)->orderByDesc('a.id')
            ->get(['a.id', 'a.title', 'a.kind', 'a.status', 'a.starts_on', 'a.ends_on', 'a.impressions', 'a.clicks', 'a.amount', 'b.name as business'])
            ->map(fn ($a) => (array) $a + ['ctr' => $a->impressions ? round($a->clicks * 100 / $a->impressions, 2) : 0]);
    }

    public function adStats(Request $r, int $id)
    {
        $ad = DB::table('business_ads as a')->join('businesses as b', 'b.id', '=', 'a.business_id')->where('a.id', $id)->where('b.family_id', $r->user()->family_id)->first(['a.id', 'a.title']) ?? abort(404);
        return ['ad' => $ad, 'daily' => DB::table('ad_stats')->where('ad_id', $id)->orderBy('day')->get(['day', 'impressions', 'clicks'])];
    }

    public function serveAds(Request $r)
    {
        $d = $r->validate(['placement' => 'required|in:featured,sponsored,banner,category_top', 'category_id' => 'nullable|integer']);
        return $this->ads->serve($this->family($r), $d['placement'], $d['category_id'] ?? null);
    }

    public function adClick(int $id)
    {
        $ad = $this->ads->live()->where('a.id', $id)->first(['a.id', 'a.link_url', 'a.business_id']) ?? abort(404);
        $this->ads->count($id, 'clicks');
        return ['link_url' => $ad->link_url, 'business_id' => $ad->business_id];
    }

    // ---------------- B2B marketplace ----------------
    public function products(Request $r)
    {
        $page = DB::table('products as p')->join('businesses as b', 'b.id', '=', 'p.business_id')->where('p.status', 'active')->where('b.status', 'active')
            ->when($r->query('q'), fn ($w, $t) => $w->where('p.title', 'like', "%$t%"))->when($r->query('category_id'), fn ($w, $v) => $w->whereIn('p.category_id', $this->descendants((int) $v)))
            ->when($r->query('business_id'), fn ($w, $v) => $w->where('p.business_id', $v))->when($r->boolean('verified'), fn ($w) => $w->where('b.seller_verified', true))
            ->orderByDesc('p.id')->limit(60)->get(['p.id', 'p.business_id', 'p.title', 'p.price', 'p.currency', 'p.unit', 'p.moq', 'p.bulk_tiers', 'p.image', 'b.name as seller', 'b.seller_verified', 'b.city']);
        return $page->map(fn ($p) => (array) $p + ['image_url' => MemberApp::photoUrl($p->image), 'bulk_tiers' => json_decode($p->bulk_tiers ?: '[]', true)]);
    }

    public function product(int $id)
    {
        $p = DB::table('products')->where(['id' => $id, 'status' => 'active'])->first() ?? abort(404);
        DB::table('products')->where('id', $id)->increment('views');
        return (array) $p + ['bulk_tiers' => json_decode($p->bulk_tiers ?: '[]', true), 'image_url' => MemberApp::photoUrl($p->image)];
    }

    public function saveProduct(Request $r, ?int $id = null)
    {
        $d = $r->validate(['business_id' => 'required|integer', 'title' => 'required|string|max:180', 'description' => 'nullable|string|max:3000', 'category_id' => 'nullable|exists:business_categories,id',
            'price' => 'nullable|numeric|min:0', 'unit' => 'nullable|string|max:20', 'moq' => 'nullable|integer|min:1', 'bulk_tiers' => 'nullable|array|max:6', 'bulk_tiers.*.min' => 'required_with:bulk_tiers|integer|min:1', 'bulk_tiers.*.price' => 'required_with:bulk_tiers|numeric|min:0', 'image' => 'nullable|image|max:3072']);
        $b = $this->own($r, $d['business_id']);
        abort_unless($b->status === 'active', 422, 'The business must be approved.');
        if ($r->hasFile('image')) $d['image'] = MemberApp::storePhoto($r->file('image'), 'products');
        $row = ['business_id' => $b->id, 'title' => $d['title'], 'description' => $d['description'] ?? null, 'category_id' => $d['category_id'] ?? $b->category_id, 'price' => $d['price'] ?? null, 'unit' => $d['unit'] ?? null,
            'moq' => $d['moq'] ?? 1, 'bulk_tiers' => json_encode($d['bulk_tiers'] ?? []), 'status' => 'pending', 'updated_at' => now()] + (isset($d['image']) ? ['image' => $d['image']] : []);
        if ($id) { abort_unless(DB::table('products')->where(['id' => $id, 'business_id' => $b->id])->update($row), 404); return ['id' => $id, 'message' => 'Updated, it will appear after approval.']; }
        return response()->json(['id' => DB::table('products')->insertGetId($row + ['created_at' => now()]), 'message' => 'Submitted, it will appear after approval.'], 201);
    }

    /** Buyer access rule: only verified members (or an active member subscription when plans are paid) may request quotations. */
    private function mayBuy(Request $r): ?string
    {
        $f = $this->family($r);
        if (config('abmm.b2b_requires_verified', true) && $f->verification_status !== 'verified') return 'Get your membership verified before requesting a quotation.';
        return null;
    }

    public function requestQuote(Request $r)
    {
        $d = $r->validate(['product_id' => 'nullable|integer', 'business_id' => 'required_without:product_id|integer', 'qty' => 'required|integer|min:1|max:10000000', 'message' => 'nullable|string|max:1000']);
        if ($why = $this->mayBuy($r)) abort(403, $why);
        $p = ! empty($d['product_id']) ? DB::table('products')->where(['id' => $d['product_id'], 'status' => 'active'])->first() ?? abort(404) : null;
        $b = Business::withoutGlobalScopes()->where('status', 'active')->findOrFail($p->business_id ?? $d['business_id']);
        abort_if($b->family_id === $r->user()->family_id, 422, 'You cannot request a quotation from your own business.');
        $id = DB::table('quotations')->insertGetId(['product_id' => $p?->id, 'business_id' => $b->id, 'buyer_user_id' => $r->user()->id, 'buyer_family_id' => $r->user()->family_id, 'qty' => $d['qty'], 'message' => $d['message'] ?? null,
            'status' => 'requested', 'created_at' => now(), 'updated_at' => now()]);
        foreach (DB::table('users')->where('family_id', $b->family_id)->where('role', 'member')->pluck('id') as $uid) UserNotification::create(['user_id' => $uid, 'title' => "New quotation request: {$d['qty']} x " . ($p->title ?? $b->name)]);
        return response()->json(['id' => $id, 'message' => 'Request sent.'], 201);
    }

    public function myQuotes(Request $r)
    {
        return DB::table('quotations as q')->join('businesses as b', 'b.id', '=', 'q.business_id')->leftJoin('products as p', 'p.id', '=', 'q.product_id')->where('q.buyer_user_id', $r->user()->id)->orderByDesc('q.id')
            ->get(['q.id', 'q.qty', 'q.status', 'q.quoted_price', 'q.response', 'q.valid_until', 'q.created_at', 'b.name as seller', 'p.title as product']);
    }

    /** Seller side: leads for own businesses. */
    public function leads(Request $r)
    {
        return DB::table('quotations as q')->join('businesses as b', 'b.id', '=', 'q.business_id')->leftJoin('products as p', 'p.id', '=', 'q.product_id')->join('users as u', 'u.id', '=', 'q.buyer_user_id')
            ->where('b.family_id', $r->user()->family_id)->when($r->query('status'), fn ($w, $v) => $w->where('q.status', $v))->orderByDesc('q.id')
            ->get(['q.id', 'q.qty', 'q.message', 'q.status', 'q.quoted_price', 'q.created_at', 'b.name as business', 'p.title as product', 'u.name as buyer']);
    }

    public function respondQuote(Request $r, int $id)
    {
        $d = $r->validate(['decision' => 'required|in:quoted,rejected,closed', 'quoted_price' => 'required_if:decision,quoted|nullable|numeric|min:0', 'response' => 'nullable|string|max:400', 'valid_until' => 'nullable|date|after_or_equal:today']);
        $q = DB::table('quotations as q')->join('businesses as b', 'b.id', '=', 'q.business_id')->where('q.id', $id)->where('b.family_id', $r->user()->family_id)->first(['q.*']) ?? abort(404);
        DB::table('quotations')->where('id', $id)->update(['status' => $d['decision'], 'quoted_price' => $d['quoted_price'] ?? null, 'response' => $d['response'] ?? null, 'valid_until' => $d['valid_until'] ?? null, 'updated_at' => now()]);
        UserNotification::create(['user_id' => $q->buyer_user_id, 'title' => 'Your quotation request has a reply (' . $d['decision'] . ').']);
        return ['message' => 'Jawab bhej diya.'];
    }

    public function buyerAccept(Request $r, int $id)
    {
        $n = DB::table('quotations')->where(['id' => $id, 'buyer_user_id' => $r->user()->id, 'status' => 'quoted'])->update(['status' => 'accepted', 'updated_at' => now()]);
        abort_unless($n, 404);
        return ['message' => 'Quotation accepted.'];
    }

    public function analytics(Request $r)
    {
        $ids = Business::withoutGlobalScopes()->where('family_id', $r->user()->family_id)->pluck('id');
        $q = DB::table('quotations')->whereIn('business_id', $ids);
        return ['business_views' => (int) DB::table('businesses')->whereIn('id', $ids)->sum('views'), 'product_views' => (int) DB::table('products')->whereIn('business_id', $ids)->sum('views'),
            'leads' => (clone $q)->count(), 'quoted' => (clone $q)->whereIn('status', ['quoted', 'accepted'])->count(), 'accepted' => (clone $q)->where('status', 'accepted')->count(),
            'ads' => ['impressions' => (int) DB::table('business_ads')->whereIn('business_id', $ids)->sum('impressions'), 'clicks' => (int) DB::table('business_ads')->whereIn('business_id', $ids)->sum('clicks')],
            'top_products' => DB::table('products')->whereIn('business_id', $ids)->orderByDesc('views')->limit(5)->get(['id', 'title', 'views'])];
    }

    // ---------------- business networking groups, meetings, referrals ----------------
    public function groups(Request $r)
    {
        $mine = DB::table('biz_group_members')->where('user_id', $r->user()->id)->pluck('role', 'group_id');
        return DB::table('biz_groups')->where('is_active', true)->when($r->query('city'), fn ($w, $v) => $w->where('city', $v))->orderBy('name')->limit(100)->get()
            ->map(fn ($g) => (array) $g + ['members' => DB::table('biz_group_members')->where('group_id', $g->id)->count(), 'my_role' => $mine[$g->id] ?? null]);
    }

    public function createGroup(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:150', 'description' => 'nullable|string|max:1000', 'city' => 'nullable|string|max:150', 'category_id' => 'nullable|exists:business_categories,id']);
        $f = $this->family($r);
        $id = DB::table('biz_groups')->insertGetId(array_merge($d, ['country' => $f->country, 'city' => $d['city'] ?? $f->city, 'owner_user_id' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]));
        DB::table('biz_group_members')->insert(['group_id' => $id, 'user_id' => $r->user()->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['id' => $id], 201);
    }

    public function joinGroup(Request $r, int $id)
    {
        abort_unless(DB::table('biz_groups')->where(['id' => $id, 'is_active' => true])->exists(), 404);
        $bid = Business::withoutGlobalScopes()->where('family_id', $r->user()->family_id)->where('status', 'active')->value('id');
        DB::table('biz_group_members')->insertOrIgnore(['group_id' => $id, 'user_id' => $r->user()->id, 'business_id' => $bid, 'role' => 'member', 'created_at' => now(), 'updated_at' => now()]);
        return ['message' => 'Joined the group.'];
    }

    public function leaveGroup(Request $r, int $id)
    {
        DB::table('biz_group_members')->where(['group_id' => $id, 'user_id' => $r->user()->id])->where('role', '!=', 'owner')->delete();
        return ['message' => 'Group chhod diya.'];
    }

    private function inGroup(Request $r, int $id, bool $owner = false): void
    {
        $role = DB::table('biz_group_members')->where(['group_id' => $id, 'user_id' => $r->user()->id])->value('role');
        abort_unless($role && (! $owner || $role === 'owner'), 403, 'You are not a member/owner of this group.');
    }

    public function groupMembers(Request $r, int $id)
    {
        $this->inGroup($r, $id);
        return DB::table('biz_group_members as m')->join('users as u', 'u.id', '=', 'm.user_id')->leftJoin('businesses as b', 'b.id', '=', 'm.business_id')->where('m.group_id', $id)->get(['u.id as user_id', 'u.name', 'm.role', 'b.name as business', 'b.type']);
    }

    public function meetings(Request $r, int $id)
    {
        $this->inGroup($r, $id);
        $mine = DB::table('biz_meeting_rsvps')->where('user_id', $r->user()->id)->pluck('status', 'meeting_id');
        return DB::table('biz_meetings')->where('group_id', $id)->where('starts_at', '>=', now()->subDay())->orderBy('starts_at')->get()
            ->map(fn ($m) => (array) $m + ['going' => DB::table('biz_meeting_rsvps')->where(['meeting_id' => $m->id, 'status' => 'going'])->count(), 'my_status' => $mine[$m->id] ?? null]);
    }

    public function createMeeting(Request $r, int $id)
    {
        $this->inGroup($r, $id, true);
        $d = $r->validate(['title' => 'required|string|max:150', 'starts_at' => 'required|date|after:now', 'venue' => 'nullable|string|max:200', 'online_url' => 'nullable|url|max:250', 'note' => 'nullable|string|max:1000']);
        abort_unless(! empty($d['venue']) || ! empty($d['online_url']), 422, 'Provide a venue or an online link.');
        $mid = DB::table('biz_meetings')->insertGetId($d + ['group_id' => $id, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach (DB::table('biz_group_members')->where('group_id', $id)->where('user_id', '!=', $r->user()->id)->pluck('user_id') as $uid) UserNotification::create(['user_id' => $uid, 'title' => "New business meeting: {$d['title']}"]);
        return response()->json(['id' => $mid], 201);
    }

    public function meetingRsvp(Request $r, int $mid)
    {
        $d = $r->validate(['status' => 'required|in:going,declined']);
        $m = DB::table('biz_meetings')->find($mid) ?? abort(404);
        $this->inGroup($r, $m->group_id);
        DB::table('biz_meeting_rsvps')->updateOrInsert(['meeting_id' => $mid, 'user_id' => $r->user()->id], ['status' => $d['status']]);
        return ['status' => $d['status']];
    }

    public function giveReferral(Request $r)
    {
        $d = $r->validate(['to_user_id' => 'required|integer', 'group_id' => 'nullable|integer', 'business_id' => 'nullable|integer', 'description' => 'required|string|max:400', 'expected_value' => 'nullable|numeric|min:0']);
        abort_if($d['to_user_id'] === $r->user()->id, 422);
        abort_unless(DB::table('users')->where(['id' => $d['to_user_id'], 'role' => 'member'])->exists(), 404);
        if (! empty($d['group_id'])) { $this->inGroup($r, $d['group_id']); abort_unless(DB::table('biz_group_members')->where(['group_id' => $d['group_id'], 'user_id' => $d['to_user_id']])->exists(), 422, 'The receiver is not in the group.'); }
        $id = DB::table('biz_referrals')->insertGetId($d + ['from_user_id' => $r->user()->id, 'status' => 'given', 'created_at' => now(), 'updated_at' => now()]);
        UserNotification::create(['user_id' => $d['to_user_id'], 'title' => "{$r->user()->name} ne aapko business referral diya."]);
        return response()->json(['id' => $id], 201);
    }

    public function referrals(Request $r)
    {
        $me = $r->user()->id;
        $rows = DB::table('biz_referrals as x')->join('users as f', 'f.id', '=', 'x.from_user_id')->join('users as t', 't.id', '=', 'x.to_user_id')->where(fn ($q) => $q->where('x.from_user_id', $me)->orWhere('x.to_user_id', $me))
            ->orderByDesc('x.id')->limit(100)->get(['x.id', 'x.description', 'x.expected_value', 'x.status', 'x.closed_value', 'x.from_user_id', 'x.to_user_id', 'f.name as from_name', 't.name as to_name', 'x.created_at']);
        return ['given' => $rows->where('from_user_id', $me)->values(), 'received' => $rows->where('to_user_id', $me)->values(),
            'summary' => ['given' => $rows->where('from_user_id', $me)->count(), 'won' => $rows->where('from_user_id', $me)->where('status', 'won')->count(), 'won_value' => (float) $rows->where('from_user_id', $me)->where('status', 'won')->sum('closed_value'),
                          'received_won_value' => (float) $rows->where('to_user_id', $me)->where('status', 'won')->sum('closed_value')]];
    }

    public function updateReferral(Request $r, int $id)
    {
        $d = $r->validate(['status' => 'required|in:contacted,won,lost', 'closed_value' => 'required_if:status,won|nullable|numeric|min:0']);
        $x = DB::table('biz_referrals')->where(['id' => $id, 'to_user_id' => $r->user()->id])->first() ?? abort(404);
        DB::table('biz_referrals')->where('id', $id)->update(['status' => $d['status'], 'closed_value' => $d['closed_value'] ?? null, 'updated_at' => now()]);
        if ($d['status'] === 'won') app(PointsService::class)->award($x->from_user_id, 'referral_won', (string) $id, 20, 'Business referral won');
        return ['message' => 'Updated.'];
    }
}
