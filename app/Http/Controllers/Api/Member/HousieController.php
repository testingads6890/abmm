<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\HousieService;
use App\Services\Payments\PaymentLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app (Milestone F): weekly Housie - tickets, live number calling, claims, sponsors, leaderboard. */
class HousieController extends Controller
{
    public function __construct(private HousieService $svc, private PaymentLedger $ledger) {}

    private function game(int $id): object { return DB::table('housie_games')->where('status', '!=', 'cancelled')->find($id) ?? abort(404); }

    public function games(Request $r)
    {
        $uid = $r->user()->id;
        return DB::table('housie_games')->where('status', '!=', 'cancelled')->where(fn ($q) => $q->whereIn('status', ['scheduled', 'running'])->orWhere('updated_at', '>=', now()->subDays(7)))->orderByRaw("CASE status WHEN 'running' THEN 0 WHEN 'scheduled' THEN 1 ELSE 2 END")->orderBy('starts_at')->limit(20)->get()
            ->map(function ($g) use ($uid) {
                $sp = $g->sponsor_id ? DB::table('housie_sponsors')->where('status', 'active')->find($g->sponsor_id) : null;
                return ['id' => $g->id, 'title' => $g->title, 'status' => $g->status, 'starts_at' => $g->starts_at, 'ticket_price' => (float) $g->ticket_price, 'currency' => $g->currency, 'max_tickets' => $g->max_tickets, 'prizes' => json_decode($g->prizes, true),
                    'my_tickets' => DB::table('housie_tickets')->where(['game_id' => $g->id, 'user_id' => $uid])->whereIn('status', ['paid', 'pending'])->count(), 'sponsor' => $sp ? ['name' => $sp->name, 'banner_url' => $sp->banner_url, 'link_url' => $sp->link_url] : null];
            })->values();
    }

    public function show(Request $r, int $id) { return $this->svc->state($this->game($id), $r->user()->id); }

    public function buy(Request $r, int $id)
    {
        $d = $r->validate(['qty' => 'required|integer|min:1|max:6', 'coupon' => 'nullable|string|max:40']);
        $g = $this->game($id);
        if ($g->ticket_price > 0 && ($ios = \App\Support\IosPolicy::guard($r, 'housie'))) return $ios;   // paid Housie is not offered on iPhone
        [$ids, $p] = $this->svc->buy($r->user(), $g, $d['qty'], $d['coupon'] ?? null);
        return $p ? ['ticket_ids' => $ids, 'payment_no' => $p->number, 'checkout' => $this->ledger->checkout($p, ['title' => $g->title])] : ['ticket_ids' => $ids, 'confirmed' => true];
    }

    public function claim(Request $r, int $id)
    {
        $d = $r->validate(['ticket_id' => 'required|integer', 'prize' => 'required|string|max:8']);
        $res = $this->svc->claim($r->user(), $this->game($id), $d['ticket_id'], $d['prize']);
        return response()->json($res + ($res['won'] ? ['message' => 'Mubarak ho!'] : ['message' => 'The claim was not valid. You will be blocked after 3 wrong claims.']), $res['won'] ? 200 : 422);
    }

    public function leaderboard(Request $r)
    {
        $r->validate(['month' => ['nullable', 'regex:/^\d{4}-\d{2}$/']]);
        return $this->svc->leaderboard($r->query('month'));
    }
}
