<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Messaging\MessageService;
use App\Services\Payments\{PaymentLedger, RazorpayGateway, StripeGateway};
use Illuminate\Http\Request;

/** Gateway & WhatsApp webhooks for the unified ledger. Add these URLs in the Razorpay / Stripe / Meta dashboards. */
class GlobalWebhookController extends Controller
{
    public function razorpay(Request $r, PaymentLedger $l)
    {
        $secret = (string) config('abmm.razorpay.webhook_secret');
        $raw = $r->getContent();
        abort_unless($secret !== '' && hash_equals(hash_hmac('sha256', $raw, $secret), (string) $r->header('X-Razorpay-Signature')), 400);
        $e = $r->json('event');
        $pay = $r->json('payload.payment.entity') ?? [];
        $p = Payment::where('gateway', 'razorpay')->where('gateway_order_id', $pay['order_id'] ?? '-')->first();
        if ($p && in_array($e, ['payment.captured', 'order.paid'], true)) $l->markPaid($p, $pay['id'] ?? null, true);
        elseif ($p && $e === 'payment.failed') $l->markFailed($p);
        return response()->json(['ok' => true]);        // unknown orders belong to the older donation webhook: always 200
    }

    public function stripe(Request $r, PaymentLedger $l)
    {
        abort_unless((new StripeGateway)->verifyWebhook($r->getContent(), (string) $r->header('Stripe-Signature')), 400);
        $type = $r->json('type'); $obj = $r->json('data.object') ?? [];
        if ($type === 'checkout.session.completed' && ($obj['payment_status'] ?? '') === 'paid') {
            $p = Payment::where('gateway', 'stripe')->where('gateway_order_id', $obj['id'] ?? '-')->first();
            if ($p) $l->markPaid($p, $obj['payment_intent'] ?? null, true);
        } elseif ($type === 'checkout.session.expired') {
            if ($p = Payment::where('gateway', 'stripe')->where('gateway_order_id', $obj['id'] ?? '-')->first()) $l->markFailed($p);
        }
        return response()->json(['ok' => true]);
    }

    public function whatsappVerify(Request $r)
    {
        $t = (string) config('abmm.whatsapp.verify_token');
        abort_unless($t !== '' && $r->query('hub_mode') === 'subscribe' && hash_equals($t, (string) $r->query('hub_verify_token')), 403);
        return response((string) $r->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function whatsapp(Request $r, MessageService $m)
    {
        $secret = (string) config('abmm.whatsapp.app_secret');
        if ($secret !== '') abort_unless(hash_equals('sha256=' . hash_hmac('sha256', $r->getContent(), $secret), (string) $r->header('X-Hub-Signature-256')), 400);
        $m->handleWhatsappWebhook($r->json()->all());
        return response()->json(['ok' => true]);
    }

    /** Stripe success/cancel landing page (browser). The real confirmation is the webhook above. */
    public function done(Request $r) { return response('Payment received. You can return to the ABMM Sampark app.'); }
    public function cancel(Request $r) { return response('Payment cancelled. You can return to the ABMM Sampark app.'); }
}
