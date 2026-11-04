<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{Family, Plan};
use App\Services\{BenefitService, CardService, SubscriptionService};
use App\Services\Payments\PaymentLedger;
use App\Services\PhoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app: digital member card (QR), renewal, vouchers & member benefits. */
class CardController extends Controller
{
    public function __construct(private CardService $cards, private BenefitService $benefits) {}

    private function family(Request $r): Family { return Family::withoutGlobalScopes()->findOrFail($r->user()->family_id); }

    public function card(Request $r) { return $this->cards->card($this->family($r)); }

    public function categories()
    {
        return DB::table('membership_categories')->where('is_active', true)->orderBy('sort')->get(['code', 'name', 'color', 'perks', 'plan_id']);
    }

    /** Renewal: pays the plan linked to the member's category (or any member plan chosen). Reminders are sent by abmm:subscriptions. */
    public function renew(Request $r, SubscriptionService $subs, PaymentLedger $ledger)
    {
        $d = $r->validate(['plan_id' => 'nullable|exists:plans,id', 'coupon' => 'nullable|string|max:30']);
        $f = $this->family($r);
        $planId = $d['plan_id'] ?? DB::table('membership_categories')->where('code', $f->member_category ?: 'general')->value('plan_id');
        $plan = Plan::active()->where('audience', 'member')->find($planId);
        abort_unless($plan, 422, 'The renewal plan is not set yet.');
        [$s, $p] = $subs->start($plan, ['user_id' => $r->user()->id, 'family_id' => $f->id, 'country' => $f->country, 'city' => $f->city, 'name' => $f->head_name, 'email' => $f->email,
            'mobile' => PhoneService::e164($f->mobile, $f->country_code ?: '+91')], $d['coupon'] ?? null);
        return ['subscription_id' => $s->id, 'payment_no' => $p->number, 'checkout' => $ledger->checkout($p, ['title' => $plan->name])];
    }

    public function benefits(Request $r)
    {
        $f = $this->family($r); $u = $r->user();
        return DB::table('benefits as b')->leftJoin('businesses as biz', 'biz.id', '=', 'b.business_id')->where('b.is_active', true)->orderByDesc('b.id')->limit(100)
            ->get(['b.*', 'biz.name as partner'])->map(fn ($b) => ['id' => $b->id, 'title' => $b->title, 'description' => $b->description, 'partner' => $b->partner, 'kind' => $b->kind, 'discount_text' => $b->discount_text,
                'valid_to' => $b->valid_to, 'eligible' => ($why = $this->benefits->blocker($b, $u, $f)) === null, 'reason' => $why]);
    }

    public function claim(Request $r, int $id)
    {
        $b = DB::table('benefits')->find($id) ?? abort(404);
        [$st, $v] = $this->benefits->claim($b, $r->user(), $this->family($r));
        return response()->json($st === 'ok' ? ['code' => $v, 'message' => 'Show this code to the partner.'] : ['message' => $v], $st === 'ok' ? 200 : 422);
    }

    public function myClaims(Request $r)
    {
        return DB::table('benefit_claims as c')->join('benefits as b', 'b.id', '=', 'c.benefit_id')->where('c.user_id', $r->user()->id)->orderByDesc('c.id')->get(['c.code', 'c.status', 'c.redeemed_at', 'c.created_at', 'b.title', 'b.discount_text']);
    }
}
