<?php

namespace App\Http\Controllers;

use App\Services\AppleIap;
use Illuminate\Http\Request;

/** App Store Server Notifications V2 endpoint: set https://DOMAIN/webhooks/apple in App Store Connect (production + sandbox URL). */
class AppleWebhookController extends Controller
{
    public function __invoke(Request $r, AppleIap $iap)
    {
        $ok = is_string($r->input('signedPayload')) && $iap->notification($r->input('signedPayload'));
        return response()->json(['ok' => $ok], $ok ? 200 : 400);
    }
}
