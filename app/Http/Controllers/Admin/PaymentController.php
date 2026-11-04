<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Payment};
use App\Services\Payments\PaymentLedger;
use App\Services\ReceiptService;
use App\Support\Exporter;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentLedger $ledger) {}

    private function query(Request $r)
    {
        $ids = $r->user()->areaFamilyIds();
        return Payment::query()->when($ids, fn ($q) => $q->whereIn('family_id', $ids))
            ->when($r->status, fn ($q, $v) => $q->where('status', $v))->when($r->purpose, fn ($q, $v) => $q->where('purpose', $v))
            ->when($r->gateway, fn ($q, $v) => $q->where('gateway', $v))->when($r->country, fn ($q, $v) => $q->where('country', $v))
            ->when($r->from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))->when($r->to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($r->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('number', 'like', "%$v%")->orWhere('payer_name', 'like', "%$v%")->orWhere('payer_mobile', 'like', "%$v%")->orWhere('gateway_payment_id', 'like', "%$v%")));
    }

    public function index(Request $r)
    {
        if (in_array($r->export, ['csv', 'xlsx', 'pdf'], true)) {
            abort_unless($r->user()->can('payments.export'), 403);
            $rows = $this->query($r)->orderByDesc('id')->limit(20000)->get()->map(fn ($p) => [$p->number, $p->created_at?->format('Y-m-d H:i'), $p->purpose, $p->payer_name, $p->payer_mobile, $p->country, $p->currency, $p->amount, $p->tax_amount, $p->discount, $p->refunded, $p->gateway, $p->gateway_payment_id, $p->status]);
            return Exporter::download($r->export, 'payments', 'Payments ledger', ['Payment no', 'Created', 'Purpose', 'Payer', 'Mobile', 'Country', 'Cur', 'Amount', 'Tax', 'Discount', 'Refunded', 'Gateway', 'Gateway ref', 'Status'], $rows);
        }
        $base = $this->query($r);
        return view('admin.global.payments', [
            'rows' => (clone $base)->orderByDesc('id')->paginate(40)->withQueryString(),
            'totals' => (clone $base)->where('status', '!=', 'created')->where('status', '!=', 'failed')->selectRaw('currency, COUNT(*) c, SUM(amount) a, SUM(refunded) r')->groupBy('currency')->get(),
        ]);
    }

    private function visible(Request $r, Payment $p): Payment
    {
        $ids = $r->user()->areaFamilyIds();
        abort_unless(! $ids || ($p->family_id && (clone $ids)->where('id', $p->family_id)->exists()), 404);
        return $p;
    }

    public function show(Request $r, Payment $payment)
    {
        $this->visible($r, $payment);
        return view('admin.global.payment', ['p' => $payment->load('refunds', 'receipt')]);
    }

    public function refund(Request $r, Payment $payment)
    {
        $this->visible($r, $payment);
        $d = $r->validate(['amount' => 'required|numeric|min:0.01', 'reason' => 'required|string|max:250']);
        try { $this->ledger->refund($payment, (float) $d['amount'], $d['reason'], $r->user()->id); }
        catch (\Illuminate\Http\Client\RequestException $e) { return back()->withErrors(['amount' => 'The gateway rejected the refund: ' . substr($e->response->body(), 0, 200)]); }
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'PAY_REFUND', 'detail' => "{$payment->number} {$d['amount']}", 'ip' => $r->ip()]);
        return back()->with('ok', 'Refunded.');
    }

    /** Cash / bank-transfer payments are confirmed by hand. */
    public function markPaid(Request $r, Payment $payment)
    {
        $this->visible($r, $payment);
        abort_unless($payment->gateway === 'manual' && $payment->status === 'created', 422, 'Only a pending manual (cash/bank) payment can be marked paid by hand.');
        $d = $r->validate(['reference' => 'required|string|max:80']);
        $this->ledger->markPaid($payment, $d['reference'], true);
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'PAY_MANUAL', 'detail' => $payment->number, 'ip' => $r->ip()]);
        return back()->with('ok', 'Marked paid, receipt sent.');
    }

    public function resend(Request $r, Payment $payment, ReceiptService $rs)
    {
        $this->visible($r, $payment);
        abort_unless($payment->status !== 'created', 422);
        $rs->issueAndDeliver($payment);
        return back()->with('ok', 'Receipt sent again (app / email / WhatsApp).');
    }

    public function receipt(Request $r, Payment $payment, ReceiptService $rs)
    {
        $this->visible($r, $payment);
        $rec = $rs->issue($payment);
        return response()->file($rs->absolute($rec), ['Content-Type' => 'application/pdf']);
    }

    public function reconcile(Request $r)
    {
        $x = $this->ledger->reconcile(0);
        return back()->with('ok', "Reconcile: {$x['checked']} check, {$x['settled']} settle, {$x['failed']} failed.");
    }
}
