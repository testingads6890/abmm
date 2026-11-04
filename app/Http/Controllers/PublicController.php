<?php

namespace App\Http\Controllers;

use App\Models\{Business, CommitteeMember, ContactMessage, Donation, Event, Family, Member, Setting};
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function home()
    {
        return view('public.home', [
            'stats' => [
                'families' => Family::where('status', 'active')->count(),
                'members' => Family::where('status', 'active')->sum('member_count'),
                'districts' => Family::distinct('district')->count('district'),
                'businesses' => Business::where('status', 'active')->count(),
            ],
            'events' => Event::where('is_public', true)->whereDate('event_date', '>=', today())->orderBy('event_date')->limit(3)->get(),
            'leaders' => CommitteeMember::where('is_active', true)->whereIn('level', ['country'])->limit(4)->get(),
            'businesses' => Business::where('status', 'active')->latest()->limit(6)->get(),
        ]);
    }

    public function committee(Request $r)
    {
        $level = $r->get('level');
        $people = CommitteeMember::where('is_active', true)->when($level, fn ($q) => $q->where('level', $level))->orderBy('level')->orderBy('name')->paginate(18)->withQueryString();
        return view('public.committee', compact('people', 'level'));
    }

    public function businesses(Request $r)
    {
        $list = Business::where('status', 'active')
            ->when($r->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('type', 'like', "%$t%")->orWhere('address', 'like', "%$t%")))
            ->when($r->type, fn ($q, $t) => $q->where('type', $t))
            ->withAvg(['reviews as rating' => fn ($q) => $q->where('status', 'approved')], 'rating')
            ->orderBy('name')->paginate(12)->withQueryString();
        $types = Business::where('status', 'active')->distinct()->orderBy('type')->pluck('type');
        return view('public.businesses', compact('list', 'types'));
    }

    public function events()
    {
        return view('public.events', ['events' => Event::where('is_public', true)->orderByDesc('event_date')->paginate(10)]);
    }

    public function contact() { return view('public.contact', ['offices' => \App\Models\Office::orderBy('sort')->orderBy('id')->get()]); }

    public function contactStore(Request $r)
    {
        $m = ContactMessage::create($r->validate(['name' => 'required|max:100', 'mobile' => 'nullable|max:15', 'email' => 'nullable|email', 'message' => 'required|max:2000']));
        \App\Models\UserNotification::notifyAdmins("New message: {$m->name}", route('admin.messages'));
        return back()->with('ok', 'Message sent. Thank you!');
    }

    public function donate() { return view('public.donate'); }

    public function donateStore(Request $r, \App\Services\RazorpayService $rzp)
    {
        $d = $r->validate(['name' => 'required|max:100', 'family_code' => 'nullable|max:12', 'amount' => 'required|numeric|min:10|max:500000', 'email' => 'required|email', 'mobile' => 'required|digits:10', 'city' => 'nullable|max:80', 'pan' => ['nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'], 'agree' => 'accepted', 'terms' => 'accepted']);
        $pan = ! empty($d['pan']) ? \Illuminate\Support\Facades\Crypt::encryptString(strtoupper($d['pan'])) : null;
        unset($d['agree'], $d['terms'], $d['pan']);
        $d['pan_enc'] = $pan;
        if (! $rzp->configured()) return back()->withInput()->withErrors(['amount' => 'Payment gateway is not configured yet.']);
        $don = Donation::create($d + ['status' => 'pending']);
        try {
            $order = $rzp->createOrder($don->amount, 'don_' . $don->id, ['donation_id' => (string) $don->id]);
        } catch (\Throwable $e) {
            report($e);
            $don->update(['status' => 'failed']);
            return back()->withInput()->withErrors(['amount' => 'Payment could not be started, please try again shortly.']);
        }
        $don->update(['razorpay_order_id' => $order['id']]);
        return view('public.pay', ['don' => $don, 'keyId' => config('abmm.razorpay.key_id'), 'order' => $order]);
    }

    /** Browser callback after Razorpay Checkout success: verify signature before marking paid. */
    public function donateVerify(Request $r, \App\Services\RazorpayService $rzp)
    {
        $d = $r->validate(['razorpay_order_id' => 'required', 'razorpay_payment_id' => 'required', 'razorpay_signature' => 'required']);
        $don = Donation::where('razorpay_order_id', $d['razorpay_order_id'])->firstOrFail();
        if ($rzp->verifyPayment($d['razorpay_order_id'], $d['razorpay_payment_id'], $d['razorpay_signature'])) {
            $don->update(['status' => 'success', 'razorpay_payment_id' => $d['razorpay_payment_id'], 'payment_ref' => $d['razorpay_payment_id'], 'paid_at' => now()]);
            \App\Models\UserNotification::notifyAdmins("Donation ₹{$don->amount} — {$don->name}", route('admin.donations.index'));
            return redirect()->route('donate')->with('ok', "Thank you! Your donation of ₹{$don->amount} was received. Payment ID: {$d['razorpay_payment_id']}");
        }
        $don->update(['status' => 'failed']);
        return redirect()->route('donate')->withErrors(['amount' => 'Payment could not be verified. If money was deducted, contact the Mahasabha.']);
    }

    /** Server-to-server webhook (payment.captured / payment.failed) — safety net if user closes the tab. */
    public function razorpayWebhook(Request $r, \App\Services\RazorpayService $rzp)
    {
        if (! $rzp->verifyWebhook($r->getContent(), (string) $r->header('X-Razorpay-Signature'))) abort(400);
        $event = $r->input('event');
        $p = $r->input('payload.payment.entity', []);
        $don = Donation::where('razorpay_order_id', $p['order_id'] ?? '')->first();
        if ($don) {
            if ($event === 'payment.captured') $don->update(['status' => 'success', 'razorpay_payment_id' => $p['id'], 'payment_ref' => $p['id'], 'paid_at' => $don->paid_at ?? now()]);
            elseif ($event === 'payment.failed' && $don->status !== 'success') $don->update(['status' => 'failed']);
        }
        if ($ts = \App\Models\TrustSubmission::where('razorpay_order_id', $p['order_id'] ?? '')->first()) {
            if ($event === 'payment.captured') app(\App\Http\Controllers\Admin\TrustController::class)->markPaid($ts, $p['id']);
            elseif ($event === 'payment.failed' && $ts->status !== 'submitted') $ts->update(['status' => 'failed']);
        }
        if ($mp = \App\Models\MatrimonyProfile::where('razorpay_order_id', $p['order_id'] ?? '')->first()) {
            if ($event === 'payment.captured') \App\Http\Controllers\Api\Member\ContentController::markParinayPaid($mp, $p['id']);
        }
        return response()->noContent();
    }

    public function page(string $slug)
    {
        abort_unless(in_array($slug, ['privacy-policy', 'terms-and-conditions', 'refund-policy']), 404);
        return view('public.page', ['title' => ucwords(str_replace('-', ' ', $slug)), 'body' => Setting::get("page_$slug", 'Update the content from Admin panel > Settings.')]);
    }
}
