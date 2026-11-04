<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/** Razorpay Orders API + signature checks, without the SDK. */
class RazorpayService
{
    public function configured(): bool
    {
        return config('abmm.razorpay.key_id') && config('abmm.razorpay.key_secret');
    }

    public function createOrder(float|string $rupees, string $receipt, array $notes = []): array
    {
        $res = Http::withBasicAuth(config('abmm.razorpay.key_id'), config('abmm.razorpay.key_secret'))->acceptJson()
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => (int) round(((float) $rupees) * 100), 'currency' => 'INR', 'receipt' => $receipt, 'notes' => $notes,
            ]);
        $res->throw();
        return $res->json();
    }

    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, (string) config('abmm.razorpay.key_secret'));
        return hash_equals($expected, $signature);
    }

    public function verifyWebhook(string $rawBody, string $signature): bool
    {
        $secret = (string) config('abmm.razorpay.webhook_secret');
        return $secret !== '' && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }
}
