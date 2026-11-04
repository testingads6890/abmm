<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{CountrySetting, Family, FormDefinition, FormSubmission, Payment, Plan, Subscription, UserNotification, Zone};
use App\Services\Messaging\MessageService;
use App\Services\Payments\PaymentLedger;
use App\Services\{PhoneService, ReceiptService, SubscriptionService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Member app (global platform): plans & subscriptions, payments + receipts, online forms, ID verification & references, messaging opt-in. */
class GlobalController extends Controller
{
    public function __construct(private PaymentLedger $ledger, private SubscriptionService $subs) {}

    private function family(Request $r): Family
    {
        return Family::withoutGlobalScopes()->findOrFail($r->user()->family_id);
    }

    private function who(Request $r): array
    {
        $f = $this->family($r); $m = $r->user();
        return ['user_id' => $m->id, 'family_id' => $f->id, 'country' => $f->country, 'city' => $f->city, 'name' => $f->head_name, 'email' => $f->email, 'mobile' => PhoneService::e164($f->mobile, $f->country_code ?: '+91')];
    }

    // ---------------- geography & language (public) ----------------
    public function geo(Request $r)
    {
        $countries = CountrySetting::where('is_active', true)->orderBy('country')->get(['country', 'iso', 'dial_code', 'currency', 'gateway', 'tax_name', 'tax_rate', 'language']);
        $zones = $r->filled('city') ? Zone::active()->where('city', $r->city)->when($r->country, fn ($q, $v) => $q->where('country', $v))->orderBy('sort')->orderBy('name')->pluck('name') : [];
        return ['countries' => $countries, 'zones' => $zones];
    }

    public function translations(string $locale)
    {
        abort_unless(preg_match('/^[a-z]{2,3}(-[A-Za-z]{2})?$/', $locale), 404);
        $file = base_path("lang/$locale.json");
        $base = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
        $db = DB::table('translations')->where('locale', $locale)->pluck('value', 'key')->all();
        if (! is_file($file) && ! $db && $locale !== 'en') return response()->json(['message' => 'Language not found.'], 404);
        return array_merge($base, $db);      // admin overrides win over the bundled lang/<locale>.json
    }

    public function setLocation(Request $r)
    {
        $d = $r->validate(['zone' => 'nullable|string|max:100', 'city' => 'nullable|string|max:150']);
        $f = $this->family($r);
        abort_unless($r->user()->member_id && DB::table('members')->where('id', $r->user()->member_id)->value('is_head'), 403, 'Only the Head of Family can change this.');
        if (! empty($d['zone'])) abort_unless(Zone::active()->where('country', $f->country)->where('city', $d['city'] ?? $f->city)->where('name', $d['zone'])->exists(), 422, 'The zone is not in this city.');
        $f->update(['zone' => $d['zone'] ?? null] + (! empty($d['city']) ? ['city' => $d['city']] : []));
        return ['zone' => $f->zone, 'city' => $f->city];
    }

    // ---------------- plans & subscriptions ----------------
    public function plans(Request $r)
    {
        $f = $this->family($r);
        $cs = CountrySetting::forCountry($f->country);
        $plans = Plan::active()->when($r->audience, fn ($q, $v) => $q->where('audience', $v),
                fn ($q) => $q->whereNotIn('audience', ['organiser', 'recruiter'])->where(fn ($w) => $w->where('audience', '!=', 'bureau')->orWhereNull('max_items')))   // partner (bureau / organiser / recruiter) plans are listed by the partner dashboards
            ->where(fn ($q) => $q->whereNull('country')->orWhereRaw('LOWER(country) = ?', [mb_strtolower((string) $f->country)]))
            ->orderBy('audience')->orderBy('sort')->orderBy('price')->get();
        return ['currency' => $cs->currency, 'tax_name' => $cs->tax_name, 'tax_rate' => $cs->tax_rate, 'gateway' => $cs->gateway, 'plans' => $plans];
    }

    public function subscribe(Request $r)
    {
        $d = $r->validate(['plan_id' => 'required|exists:plans,id', 'coupon' => 'nullable|string|max:30', 'subscriber_id' => 'nullable|integer']);
        $plan = Plan::active()->findOrFail($d['plan_id']);
        if ($plan->price > 0 && $plan->audience === 'training' && ($ios = \App\Support\IosPolicy::guard($r, 'training', 'plan', $plan->id))) return $ios;   // iPhone: Apple In-App Purchase rule
        abort_unless($this->subs->ownsSubscriber($plan->audience, $d['subscriber_id'] ?? null, $r->user()->id, $r->user()->family_id), 422, 'Choose your listing (subscriber_id) for this plan.');
        $who = $this->who($r) + ['subscriber_id' => $d['subscriber_id'] ?? null];
        [$s, $p] = $this->subs->start($plan, $who, $d['coupon'] ?? null);
        $co = $this->ledger->checkout($p, ['title' => $plan->name]);
        return ['subscription_id' => $s->id, 'payment' => $this->pay($p->fresh()), 'checkout' => $co];
    }

    public function mySubscriptions(Request $r)
    {
        return Subscription::with('plan:id,name,period,audience')->where('family_id', $r->user()->family_id)->orderByDesc('id')->get();
    }

    // ---------------- payments & receipts ----------------
    private function pay(Payment $p): array
    {
        return ['number' => $p->number, 'purpose' => $p->purpose, 'amount' => $p->amount, 'currency' => $p->currency, 'tax' => $p->tax_amount, 'discount' => $p->discount, 'status' => $p->status,
            'gateway' => $p->gateway, 'paid_at' => $p->paid_at, 'receipt' => $p->status === 'paid' || $p->refunded > 0 ? url("/api/v1/m/payments/{$p->number}/receipt") : null];
    }

    public function payments(Request $r)
    {
        return Payment::where('family_id', $r->user()->family_id)->orderByDesc('id')->limit(100)->get()->map(fn ($p) => $this->pay($p));
    }

    private function mine(Request $r, string $number): Payment
    {
        return Payment::where('number', $number)->where('family_id', $r->user()->family_id)->firstOrFail();
    }

    /** Razorpay: app sends razorpay_order_id / payment_id / signature after the checkout. Stripe is confirmed server-side by webhook. */
    public function verifyPayment(Request $r)
    {
        $d = $r->validate(['payment_no' => 'required|string|max:24']);
        $p = $this->mine($r, $d['payment_no']);
        if ($p->status === 'paid') return $this->pay($p);
        $gw = $this->ledger->gateway($p->gateway);
        $pid = $gw->verify($r->all());
        abort_unless($pid && $r->input('razorpay_order_id') === $p->gateway_order_id, 422, 'Payment was not verified.');
        $this->ledger->markPaid($p, $pid);
        return $this->pay($p->fresh());
    }

    public function paymentStatus(Request $r, string $number) { return $this->pay($this->mine($r, $number)); }

    public function receipt(Request $r, string $number, ReceiptService $rs)
    {
        $p = $this->mine($r, $number);
        abort_unless(in_array($p->status, ['paid', 'partial_refund', 'refunded'], true), 404);
        return response()->file($rs->absolute($rs->issue($p)), ['Content-Type' => 'application/pdf']);
    }

    public function sendReceipt(Request $r, string $number, ReceiptService $rs)
    {
        $p = $this->mine($r, $number);
        abort_unless($p->status === 'paid', 422);
        $rs->issueAndDeliver($p);
        return ['message' => 'Receipt sent by email / WhatsApp.'];
    }

    // ---------------- online forms ----------------
    public function forms(Request $r)
    {
        $f = $this->family($r);
        return FormDefinition::active()->where(fn ($q) => $q->whereNull('country')->orWhereRaw('LOWER(country) = ?', [mb_strtolower((string) $f->country)]))
            ->where(fn ($q) => $q->whereNull('city')->orWhereRaw('LOWER(city) = ?', [mb_strtolower((string) $f->city)]))->orderBy('title')->get(['slug', 'title', 'description', 'fee', 'currency', 'needs_approval', 'fields']);
    }

    public function formSubmit(Request $r, string $slug)
    {
        $form = FormDefinition::active()->where('slug', $slug)->firstOrFail();
        $rules = [];
        foreach ($form->fields as $fl) {
            $base = match ($fl['type']) {
                'number' => ['numeric'], 'date' => ['date'], 'checkbox' => ['boolean'], 'file' => ['file', 'max:4096', 'mimes:jpg,jpeg,png,pdf'],
                'select' => [Rule::in($fl['options'] ?? [])], 'textarea' => ['string', 'max:3000'], default => ['string', 'max:500'],
            };
            $rules[$fl['key']] = array_merge([$fl['required'] ? 'required' : 'nullable'], $base);
        }
        $in = $r->validate($rules + ['coupon' => 'nullable|string|max:30']);
        $fam = $this->family($r);
        $data = [];
        foreach ($form->fields as $fl) {
            $k = $fl['key'];
            $data[$k] = $fl['type'] === 'file' && $r->hasFile($k) ? $r->file($k)->storeAs('private/forms/' . $form->id, Str::uuid() . '.' . $r->file($k)->extension(), 'local') : ($in[$k] ?? null);
        }
        $sub = FormSubmission::create(['reference' => strtoupper(Str::random(8)), 'form_id' => $form->id, 'user_id' => $r->user()->id, 'family_id' => $fam->id, 'country' => $fam->country, 'state' => $fam->state,
            'city' => $fam->city, 'zone' => $fam->zone, 'data' => $data, 'status' => $form->needs_approval ? 'pending' : 'approved', 'payment_status' => $form->fee > 0 ? 'due' : 'none']);
        $out = ['reference' => $sub->reference, 'status' => $sub->status];
        app(\App\Services\PointsService::class)->award($r->user(), 'form_submit', (string) $sub->id);
        if ($form->fee > 0) {
            $p = $this->ledger->create(['purpose' => 'form', 'purpose_ref' => (string) $sub->id, 'amount' => $form->fee, 'currency' => $form->currency, 'coupon' => $in['coupon'] ?? null] + $this->who($r) + ['meta' => ['title' => $form->title]]);
            $out['payment'] = $this->pay($p) + ['checkout' => $this->ledger->checkout($p, ['title' => $form->title])];
        }
        UserNotification::notifyAdmins("New form: {$form->title} ({$sub->reference})", route('admin.forms.submissions'), $fam->pradesh);
        return $out;
    }

    public function myForms(Request $r)
    {
        return FormSubmission::with('form:id,title')->where('family_id', $r->user()->family_id)->orderByDesc('id')->limit(100)->get(['id', 'reference', 'form_id', 'status', 'payment_status', 'remark', 'created_at']);
    }

    // ---------------- verification, references, membership card ----------------
    public function verification(Request $r)
    {
        $f = $this->family($r);
        return ['status' => $f->verification_status, 'membership_no' => $f->membership_no, 'category' => $f->member_category, 'chapter' => $f->chapter, 'note' => $f->verification_note,
            'id_proof_type' => $f->id_proof_type, 'has_id_proof' => (bool) $f->id_proof_path,
            'references' => DB::table('member_references')->where('family_id', $f->id)->get(['id', 'referee_name', 'referee_mobile', 'status'])];
    }

    public function idProof(Request $r)
    {
        $d = $r->validate(['type' => 'required|in:aadhaar,passport,driving_licence,voter_id,national_id,other', 'file' => 'required|file|max:5120|mimes:jpg,jpeg,png,pdf']);
        $f = $this->family($r);
        abort_if($f->verification_status === 'verified', 422, 'You are already verified.');
        if ($f->id_proof_path) Storage::disk('local')->delete($f->id_proof_path);
        $path = $r->file('file')->storeAs('private/idproof', Str::uuid() . '.' . $r->file('file')->extension(), 'local');
        $f->update(['id_proof_type' => $d['type'], 'id_proof_path' => $path, 'verification_status' => 'submitted']);
        return ['status' => 'submitted'];
    }

    /** Two existing verified members must vouch for the applicant. */
    public function references(Request $r)
    {
        $d = $r->validate(['references' => 'required|array|size:2', 'references.*.name' => 'required|string|max:150', 'references.*.mobile' => ['required', 'regex:/^\d{6,14}$/'],
            'references.*.country_code' => ['nullable', 'regex:/^\+\d{1,4}$/']]);
        $f = $this->family($r);
        $mobiles = array_map(fn ($x) => $x['mobile'], $d['references']);
        abort_if(count(array_unique($mobiles)) < 2, 422, 'Provide two different members.');
        abort_if(in_array($f->mobile, $mobiles, true), 422, 'You cannot make yourself a reference.');
        DB::transaction(function () use ($f, $d) {
            DB::table('member_references')->where('family_id', $f->id)->where('status', '!=', 'confirmed')->delete();
            foreach ($d['references'] as $x) {
                $ref = Family::withoutGlobalScopes()->where('mobile', $x['mobile'])->where('verification_status', 'verified')->first();
                $id = DB::table('member_references')->insertGetId(['family_id' => $f->id, 'referee_family_id' => $ref?->id, 'referee_name' => $x['name'], 'referee_mobile' => $x['mobile'], 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                if ($ref && ($u = \App\Models\User::where('role', 'member')->where('family_id', $ref->id)->first()))
                    UserNotification::create(['user_id' => $u->id, 'title' => "{$f->head_name} has named you as a reference. Please confirm in the app."]);
            }
        });
        return $this->verification($r);
    }

    /** Requests where I am the referee. */
    public function referenceRequests(Request $r)
    {
        $f = $this->family($r);
        abort_unless($f->verification_status === 'verified', 403, 'Only a verified member can give a reference.');
        return DB::table('member_references as m')->join('families as a', 'a.id', '=', 'm.family_id')->where('m.referee_mobile', $f->mobile)->where('m.status', 'pending')
            ->get(['m.id', 'a.head_name as applicant', 'a.city', 'a.country']);
    }

    public function referenceRespond(Request $r, int $id)
    {
        $d = $r->validate(['accept' => 'required|boolean']);
        $f = $this->family($r);
        abort_unless($f->verification_status === 'verified', 403);
        $ok = DB::table('member_references')->where('id', $id)->where('referee_mobile', $f->mobile)->where('status', 'pending')
            ->update(['status' => $d['accept'] ? 'confirmed' : 'declined', 'referee_family_id' => $f->id, 'responded_at' => now(), 'updated_at' => now()]);
        abort_unless($ok, 404);
        return ['message' => 'Dhanyavaad.'];
    }

    // ---------------- messaging opt-in ----------------
    public function optIn(Request $r, MessageService $m)
    {
        $d = $r->validate(['channel' => 'required|in:whatsapp,sms', 'opted_in' => 'required|boolean']);
        $f = $this->family($r);
        $m->setOptIn(PhoneService::e164($f->mobile, $f->country_code ?: '+91'), $d['channel'], (bool) $d['opted_in']);
        return ['channel' => $d['channel'], 'opted_in' => (bool) $d['opted_in']];
    }
}
