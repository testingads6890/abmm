<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\AppleIap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app (iOS): list App Store products and redeem a purchase made through StoreKit. */
class IapController extends Controller
{
    public function products()
    {
        return DB::table('iap_products')->where('is_active', true)->get(['product_id', 'kind', 'ref_id', 'title']);
    }

    public function redeem(Request $r, AppleIap $iap)
    {
        $d = $r->validate(['signed_transaction' => 'required|string|max:20000']);
        $res = $iap->redeem($r->user(), $d['signed_transaction']);
        return response()->json($res, $res['ok'] ? 200 : 422);
    }
}
