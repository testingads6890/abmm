<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Family};
use App\Services\{BenefitService, CardService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member-card tools: scan/lookup a card, assign membership category, redeem a voucher code; public QR verification page. */
class CardAdminController extends Controller
{
    public function index() { return view('admin.community.card', ['cats' => DB::table('membership_categories')->where('is_active', true)->orderBy('sort')->get()]); }

    public function lookup(Request $r, CardService $cards)
    {
        $d = $r->validate(['token' => 'required|string|max:60']);
        $f = $cards->resolve($d['token']);
        if (! $f || ! $this->inArea($r, $f)) return response()->json(['valid' => false, 'message' => 'Invalid card, or not in your area.'], 404);
        return ['valid' => true] + $cards->card($f);
    }

    private function inArea(Request $r, Family $f): bool
    {
        $ids = $r->user()->areaFamilyIds();
        return ! $ids || (clone $ids)->where('id', $f->id)->exists();
    }

    public function assign(Request $r)
    {
        $d = $r->validate(['family_code' => 'required|string|max:20', 'member_category' => 'required|exists:membership_categories,code']);
        $f = Family::where('family_code', $d['family_code'])->first();
        if (! $f) return back()->withErrors(['family_code' => 'Family not found (or not in your area).']);
        $f->update(['member_category' => $d['member_category']]);
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'MEMBER_CATEGORY', 'detail' => substr("{$f->family_code} => {$d['member_category']}", 0, 250), 'ip' => $r->ip()]);
        return back()->with('ok', 'Category set.');
    }

    public function redeem(Request $r, BenefitService $svc)
    {
        $d = $r->validate(['code' => 'required|string|max:20']);
        [$st, $c] = $svc->redeem($d['code'], $r->user()->id);
        $msg = ['ok' => 'Voucher redeemed.', 'duplicate' => 'This voucher has already been used.', 'void' => 'This voucher is cancelled.', 'invalid' => 'Wrong code.'][$st];
        return $r->wantsJson() ? response()->json(['result' => $st, 'message' => $msg], $st === 'ok' ? 200 : 422) : back()->with($st === 'ok' ? 'ok' : 'err', $msg)->withErrors($st === 'ok' ? [] : ['code' => $msg]);
    }

    /** Public: what a shop / event gate sees after scanning a member's card QR. No contact details are shown. */
    public function verifyPage(string $token, CardService $cards)
    {
        $f = $cards->resolve($token);
        return response()->view('verify-card', ['c' => $f ? $cards->card($f) : null], $f ? 200 : 404);
    }
}
