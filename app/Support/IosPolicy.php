<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Apple App Store rule 3.1.1: digital content / features unlocked inside the iPhone app must be sold through In-App Purchase.
 * The Flutter app sends header  X-Platform: ios  (or android). For iOS requests that would start a card/UPI payment for a digital purpose
 * the API answers instead with:
 *   mode "external": 403 {code:"use_web", url}      - member buys on the website; access shows up in the app (no purchase button on iOS)
 *   mode "iap":      409 {code:"use_iap", product_id} - the app starts the StoreKit purchase, then calls /api/v1/m/iap/apple/redeem
 *   mode "off":      no restriction (e.g. you decide not to publish on iOS, or Apple confirmed an exemption)
 * Event tickets, donations (registered trusts) and real-world services are NOT digital purposes.
 */
class IosPolicy
{
    public static function isIos(Request $r): bool { return strtolower((string) ($r->header('X-Platform') ?: $r->header('X-App-Platform'))) === 'ios'; }

    public static function mode(): string { return in_array($m = (string) config('abmm.ios.mode'), ['external', 'iap', 'off'], true) ? $m : 'external'; }

    /** @return \Illuminate\Http\JsonResponse|null a response to return when the purchase must not continue on iOS */
    public static function guard(Request $r, string $purpose, ?string $kind = null, ?int $refId = null)
    {
        if (! self::isIos($r) || self::mode() === 'off') return null;
        $purposes = (array) config('abmm.ios.purposes');
        if (! in_array($purpose, $purposes, true)) return null;
        if ($purpose === 'housie') return response()->json(['code' => 'not_on_ios', 'message' => 'Paid Housie tickets are not available in the iPhone app. You can play free games.'], 403);
        if (self::mode() === 'iap' && $kind && $refId) {
            $p = \Illuminate\Support\Facades\DB::table('iap_products')->where(['kind' => $kind, 'ref_id' => $refId, 'is_active' => true])->first();
            if ($p) return response()->json(['code' => 'use_iap', 'product_id' => $p->product_id, 'message' => 'Buy from the App Store.'], 409);
        }
        return response()->json(['code' => 'use_web', 'url' => rtrim((string) config('abmm.ios.web_url') ?: config('app.url'), '/') . '/m/buy', 'message' => 'On iPhone, please buy this from the website. Access will appear in the app after purchase.'], 403);
    }
}
