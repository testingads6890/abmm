<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{Family, UserNotification};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app: event vendor directory (caterers, decorators, photographers, pandits ...), quote requests, reviews and the vendor dashboard. */
class VendorController extends Controller
{
    public const CATEGORIES = ['caterer' => 'Caterer / Halwai', 'decorator' => 'Decorator', 'photographer' => 'Photographer / Videographer', 'pandit' => 'Pandit / Purohit', 'dj' => 'DJ / Music / Band', 'tent' => 'Tent & Furniture',
        'mehendi' => 'Mehendi / Makeup', 'transport' => 'Transport', 'venue' => 'Banquet / Venue', 'florist' => 'Florist', 'invitation' => 'Invitation cards', 'other' => 'Other'];

    private function visible()
    {
        $q = DB::table('event_vendors')->where('status', 'active');
        if (config('abmm.listing_requires_subscription') && DB::table('plans')->where('audience', 'vendor')->where('is_active', true)->exists()) $q->whereDate('listing_until', '>=', today());
        return $q;
    }

    private function shape(object $v): array
    {
        return ['id' => $v->id, 'category' => $v->category, 'name' => $v->name, 'city' => $v->city, 'country' => $v->country, 'description' => $v->description, 'services' => json_decode($v->services ?: '[]', true), 'price_from' => $v->price_from,
            'currency' => $v->currency, 'verified' => (bool) $v->verified, 'top' => $v->top_until && $v->top_until >= today()->toDateString(), 'rating' => (float) $v->rating_avg, 'reviews' => (int) $v->rating_count];
    }

    public function categories() { return collect(self::CATEGORIES)->map(fn ($n, $k) => ['code' => $k, 'name' => $n])->values(); }

    public function index(Request $r)
    {
        $q = $this->visible()->when($r->query('category'), fn ($w, $v) => $w->where('category', $v))->when($r->query('city'), fn ($w, $v) => $w->where('city', $v))->when($r->query('country'), fn ($w, $v) => $w->where('country', $v))
            ->when($r->query('q'), fn ($w, $v) => $w->where('name', 'like', "%$v%"))->when($r->boolean('verified'), fn ($w) => $w->where('verified', true));
        // paid top placement first, then verified, then best rated
        return $q->orderByRaw('CASE WHEN top_until IS NOT NULL AND top_until >= ? THEN 0 ELSE 1 END', [today()->toDateString()])->orderByDesc('verified')->orderByDesc('rating_avg')->limit(100)->get()->map(fn ($v) => $this->shape($v));
    }

    public function show(int $id)
    {
        $v = $this->visible()->find($id) ?? abort(404);
        DB::table('event_vendors')->where('id', $id)->increment('views');
        return $this->shape($v) + ['phone' => $v->phone, 'gallery' => json_decode($v->gallery ?: '[]', true),
            'recent_reviews' => DB::table('vendor_reviews as x')->join('users as u', 'u.id', '=', 'x.user_id')->where(['x.vendor_id' => $id, 'x.status' => 'approved'])->orderByDesc('x.id')->limit(10)->get(['u.name', 'x.rating', 'x.comment', 'x.created_at'])];
    }

    public function register(Request $r)
    {
        $d = $r->validate(['category' => 'required|in:' . implode(',', array_keys(self::CATEGORIES)), 'name' => 'required|string|max:150', 'contact_person' => 'nullable|string|max:100', 'phone' => 'required|string|max:30', 'email' => 'nullable|email|max:150',
            'city' => 'nullable|string|max:150', 'zone' => 'nullable|string|max:100', 'description' => 'nullable|string|max:3000', 'services' => 'nullable|array|max:30', 'services.*' => 'string|max:80', 'price_from' => 'nullable|numeric|min:0']);
        $f = Family::withoutGlobalScopes()->findOrFail($r->user()->family_id);
        $id = DB::table('event_vendors')->insertGetId(array_merge($d, ['services' => json_encode($d['services'] ?? []), 'user_id' => $r->user()->id, 'family_id' => $f->id, 'country' => $f->country, 'city' => $d['city'] ?? $f->city, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
        return response()->json(['id' => $id, 'message' => 'Submitted. It will appear after approval.'], 201);
    }

    public function update(Request $r, int $id)
    {
        $d = $r->validate(['name' => 'sometimes|string|max:150', 'phone' => 'sometimes|string|max:30', 'description' => 'nullable|string|max:3000', 'services' => 'nullable|array|max:30', 'services.*' => 'string|max:80', 'price_from' => 'nullable|numeric|min:0']);
        if (isset($d['services'])) $d['services'] = json_encode($d['services']);
        abort_unless(DB::table('event_vendors')->where(['id' => $id, 'user_id' => $r->user()->id])->update($d + ['updated_at' => now()]), 404);
        return ['message' => 'Updated.'];
    }

    public function quote(Request $r, int $id)
    {
        $d = $r->validate(['event_date' => 'nullable|date|after_or_equal:today', 'event_type' => 'nullable|string|max:60', 'budget' => 'nullable|numeric|min:0', 'message' => 'nullable|string|max:1000']);
        $v = $this->visible()->find($id) ?? abort(404);
        abort_if($v->user_id === $r->user()->id, 422);
        abort_if(DB::table('vendor_quotes')->where(['vendor_id' => $id, 'user_id' => $r->user()->id, 'status' => 'new'])->where('created_at', '>=', now()->subDay())->exists(), 422, 'You have just sent a request.');
        $qid = DB::table('vendor_quotes')->insertGetId($d + ['vendor_id' => $id, 'user_id' => $r->user()->id, 'status' => 'new', 'created_at' => now(), 'updated_at' => now()]);
        if ($v->user_id) UserNotification::create(['user_id' => $v->user_id, 'title' => 'New lead: ' . ($d['event_type'] ?? 'Event') . ($d['event_date'] ?? false ? " ({$d['event_date']})" : '') . ' - ' . $v->name]);   // lead alert
        return response()->json(['id' => $qid, 'message' => 'Request sent.'], 201);
    }

    public function myQuotes(Request $r)
    {
        return DB::table('vendor_quotes as q')->join('event_vendors as v', 'v.id', '=', 'q.vendor_id')->where('q.user_id', $r->user()->id)->orderByDesc('q.id')->get(['q.id', 'v.name', 'q.event_date', 'q.status', 'q.quote_amount', 'q.response']);
    }

    // ---------------- vendor dashboard ----------------
    public function dashboard(Request $r)
    {
        $ids = DB::table('event_vendors')->where('user_id', $r->user()->id)->pluck('id');
        abort_if($ids->isEmpty(), 404, 'You do not have a vendor profile.');
        $q = DB::table('vendor_quotes')->whereIn('vendor_id', $ids);
        return ['vendors' => DB::table('event_vendors')->whereIn('id', $ids)->get(['id', 'name', 'category', 'status', 'verified', 'listing_until', 'top_until', 'views', 'rating_avg', 'rating_count']),
            'leads' => ['total' => (clone $q)->count(), 'new' => (clone $q)->where('status', 'new')->count(), 'quoted' => (clone $q)->where('status', 'quoted')->count(), 'accepted' => (clone $q)->where('status', 'accepted')->count()],
            'recent' => DB::table('vendor_quotes as q')->join('users as u', 'u.id', '=', 'q.user_id')->whereIn('q.vendor_id', $ids)->orderByDesc('q.id')->limit(30)->get(['q.id', 'u.name', 'q.event_date', 'q.event_type', 'q.budget', 'q.message', 'q.status'])];
    }

    public function respond(Request $r, int $qid)
    {
        $d = $r->validate(['decision' => 'required|in:quoted,declined', 'quote_amount' => 'required_if:decision,quoted|nullable|numeric|min:0', 'response' => 'nullable|string|max:400']);
        $q = DB::table('vendor_quotes as q')->join('event_vendors as v', 'v.id', '=', 'q.vendor_id')->where('q.id', $qid)->where('v.user_id', $r->user()->id)->first(['q.*']) ?? abort(404);
        DB::table('vendor_quotes')->where('id', $qid)->update(['status' => $d['decision'], 'quote_amount' => $d['quote_amount'] ?? null, 'response' => $d['response'] ?? null, 'updated_at' => now()]);
        UserNotification::create(['user_id' => $q->user_id, 'title' => 'The vendor replied to your request (' . $d['decision'] . ').']);
        return ['message' => 'Jawab bhej diya.'];
    }

    public function acceptQuote(Request $r, int $qid)
    {
        abort_unless(DB::table('vendor_quotes')->where(['id' => $qid, 'user_id' => $r->user()->id, 'status' => 'quoted'])->update(['status' => 'accepted', 'updated_at' => now()]), 404);
        return ['message' => 'Quote accepted.'];
    }

    public function review(Request $r, int $id)
    {
        $d = $r->validate(['rating' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:500']);
        $v = DB::table('event_vendors')->where(['id' => $id, 'status' => 'active'])->first() ?? abort(404);
        abort_if($v->user_id === $r->user()->id, 422);
        abort_unless(DB::table('vendor_quotes')->where(['vendor_id' => $id, 'user_id' => $r->user()->id])->whereIn('status', ['quoted', 'accepted'])->exists(), 403, 'Get a quote from the vendor before reviewing.');
        DB::table('vendor_reviews')->updateOrInsert(['vendor_id' => $id, 'user_id' => $r->user()->id], $d + ['status' => 'approved', 'updated_at' => now(), 'created_at' => now()]);
        $agg = DB::table('vendor_reviews')->where(['vendor_id' => $id, 'status' => 'approved'])->selectRaw('AVG(rating) a, COUNT(*) c')->first();
        DB::table('event_vendors')->where('id', $id)->update(['rating_avg' => round((float) $agg->a, 2), 'rating_count' => (int) $agg->c]);
        return ['message' => 'Review saved.'];
    }
}
