<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PartnerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin dashboards for Marriage Bureaus, Event Organisers and Job Recruiters: KPIs, subscription revenue, approvals, state-wise split. */
class PartnerAdminController extends Controller
{
    private const MANAGE = ['bureau' => 'bureaus', 'organiser' => 'organisers', 'recruiter' => 'recruiters'];

    public function index(Request $r, string $kind)
    {
        abort_unless(isset(PartnerService::KINDS[$kind]), 404);
        $scoped = fn () => ManageController::scopeArea(DB::table('partners')->where('kind', $kind), 'partners');   // sub-admins: own area only
        $ids = $scoped()->pluck('id');
        $live = DB::table('subscriptions')->where(['subscriber_type' => $kind, 'status' => 'active'])->whereIn('subscriber_id', $ids)->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', today()->toDateString()));
        $tbl = PartnerService::KINDS[$kind]['table'];
        $subIds = DB::table('subscriptions')->where('subscriber_type', $kind)->whereIn('subscriber_id', $ids)->pluck('id')->map(fn ($i) => (string) $i);
        $payQ = DB::table('payments as p')->where('p.purpose', 'subscription')->where('p.status', 'paid')->whereIn('p.purpose_ref', $subIds);
        $kpi = [
            'partners' => $ids->count(), 'active' => $scoped()->where('status', 'active')->count(), 'pending' => $scoped()->where('status', 'pending')->count(), 'blocked' => $scoped()->where('status', 'blocked')->count(),
            'subscribed' => (clone $live)->distinct()->count('subscriber_id'), 'records' => DB::table($tbl)->whereIn('partner_id', $ids)->count(),
            'leads' => DB::table('partner_enquiries')->whereIn('partner_id', $ids)->count(), 'revenue' => (float) (clone $payQ)->sum('p.amount'),
            'revenue_month' => (float) (clone $payQ)->where('p.paid_at', '>=', now()->startOfMonth())->sum('p.amount'),
        ];
        $kpi['expiring'] = (clone $live)->whereNotNull('ends_on')->whereBetween('ends_on', [today()->toDateString(), today()->addDays(15)->toDateString()])->count();
        $byState = $scoped()->selectRaw("COALESCE(pradesh, 'Unknown') as state, COUNT(*) as n")->groupBy('pradesh')->orderByDesc('n')->get();
        $partners = $scoped()->select('partners.*')->selectRaw("(select max(s.ends_on) from subscriptions s where s.subscriber_type = partners.kind and s.subscriber_id = partners.id and s.status = 'active') as sub_until")
            ->selectRaw("(select count(*) from $tbl r where r.partner_id = partners.id) as records")->selectRaw('(select count(*) from partner_enquiries e where e.partner_id = partners.id) as leads')->orderByDesc('id')->limit(50)->get();
        $plans = DB::table('plans')->where('audience', $kind)->orderBy('sort')->orderBy('price')->get()
            ->map(fn ($p) => (object) ((array) $p + ['subs' => DB::table('subscriptions')->where(['plan_id' => $p->id, 'status' => 'active'])->whereIn('subscriber_id', $ids)->count()]));
        return view('admin.partners.dashboard', ['kind' => $kind, 'meta' => PartnerService::KINDS[$kind], 'kpi' => $kpi, 'byState' => $byState, 'partners' => $partners, 'plans' => $plans, 'manage' => self::MANAGE[$kind]]);
    }
}
