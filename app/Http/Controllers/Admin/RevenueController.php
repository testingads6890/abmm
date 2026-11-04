<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Payment, Subscription};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Revenue dashboard: collections by country / purpose / month, active subscribers, refunds, expiring plans. Scoped to the viewer's area. */
class RevenueController extends Controller
{
    public function index(Request $r)
    {
        $ids = $r->user()->areaFamilyIds();
        $from = $r->from ?: now()->startOfYear()->toDateString(); $to = $r->to ?: now()->toDateString();
        $paid = fn () => Payment::query()->when($ids, fn ($q) => $q->whereIn('family_id', $ids))->whereIn('status', ['paid', 'partial_refund', 'refunded'])
            ->whereDate('paid_at', '>=', $from)->whereDate('paid_at', '<=', $to);

        $driver = DB::connection()->getDriverName();
        $month = $driver === 'sqlite' ? "strftime('%Y-%m', paid_at)" : "DATE_FORMAT(paid_at, '%Y-%m')";

        return view('admin.global.revenue', [
            'from' => $from, 'to' => $to,
            'byCurrency' => $paid()->selectRaw('currency, COUNT(*) c, SUM(amount) gross, SUM(refunded) refunded, SUM(tax_amount) tax, SUM(discount) discount')->groupBy('currency')->get(),
            'byCountry' => $paid()->selectRaw('country, currency, COUNT(*) c, SUM(amount - refunded) net')->groupBy('country', 'currency')->orderByDesc('net')->get(),
            'byPurpose' => $paid()->selectRaw('purpose, currency, COUNT(*) c, SUM(amount - refunded) net')->groupBy('purpose', 'currency')->orderByDesc('net')->get(),
            'byMonth' => $paid()->selectRaw("$month m, currency, SUM(amount - refunded) net")->groupBy('m', 'currency')->orderBy('m')->get(),
            'subs' => Subscription::query()->when($ids, fn ($q) => $q->whereIn('family_id', $ids))->selectRaw('subscriber_type, status, COUNT(*) c')->groupBy('subscriber_type', 'status')->get(),
            'expiring' => Subscription::with('plan')->when($ids, fn ($q) => $q->whereIn('family_id', $ids))->where('status', 'active')->whereBetween('ends_on', [today(), today()->addDays(30)])->count(),
            'pending' => Payment::query()->when($ids, fn ($q) => $q->whereIn('family_id', $ids))->where('status', 'created')->count(),
        ]);
    }
}
