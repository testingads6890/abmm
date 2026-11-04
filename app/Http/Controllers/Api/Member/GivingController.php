<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\GivingService;
use App\Services\Payments\PaymentLedger;
use App\Support\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, DB};

/** Member app (Milestone E): donation campaigns, 80G certificates, utilisation reports, relief / assistance requests, volunteers. */
class GivingController extends Controller
{
    public function __construct(private GivingService $svc, private PaymentLedger $ledger) {}

    private const PAN = '/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/';

    private function item(object $c): array
    {
        $e = DB::table('donation_entities')->find($c->entity_id);
        return ['id' => $c->id, 'title' => $c->title, 'category' => $c->category, 'story' => $c->story, 'beneficiary' => $c->beneficiary, 'banner' => $c->banner, 'currency' => $c->currency, 'featured' => (bool) $c->featured,
            'starts_on' => $c->starts_on, 'ends_on' => $c->ends_on, 'city' => $c->city, 'status' => $c->status, 'progress' => $this->svc->progress($c),
            'entity' => $e ? ['name' => $e->name, 'eligible_80g' => $this->svc->is80g($e, $c->currency), 'g80_number' => $this->svc->is80g($e, $c->currency) ? $e->g80_number : null] : null];
    }

    public function campaigns(Request $r)
    {
        $r->validate(['category' => 'nullable|in:education,medical,relief,temple,other', 'city' => 'nullable|string|max:100', 'featured' => 'nullable|boolean', 'status' => 'nullable|in:live,completed']);
        return DB::table('campaigns')->where('status', $r->query('status') ?: 'live')
            ->when($r->query('category'), fn ($q, $v) => $q->where('category', $v))->when($r->query('city'), fn ($q, $v) => $q->where('city', 'like', str_replace(['%', '_'], ['\%', '\_'], trim($v)) . '%'))
            ->when($r->boolean('featured'), fn ($q) => $q->where('featured', true))->orderByDesc('featured')->orderByDesc('id')->limit(100)->get()->map(fn ($c) => $this->item($c))->values();
    }

    public function show(Request $r, int $id)
    {
        $c = DB::table('campaigns')->whereIn('status', ['live', 'completed', 'closed'])->find($id) ?? abort(404);
        $donors = DB::table('campaign_donations')->where(['campaign_id' => $id, 'status' => 'paid'])->orderByDesc('id')->limit(10)->get()
            ->map(fn ($d) => ['name' => $d->anonymous ? 'Anonymous' : $d->donor_name, 'amount' => (float) $d->amount, 'at' => $d->paid_at]);
        return $this->item($c) + ['recent_donors' => $donors, 'reports' => DB::table('utilisation_reports')->where(['campaign_id' => $id, 'published' => true])->orderByDesc('report_date')->get(['id', 'title', 'amount_spent', 'details', 'document_url', 'report_date']),
            'volunteers' => DB::table('volunteers')->where(['campaign_id' => $id, 'status' => 'active'])->count()];
    }

    public function donate(Request $r, int $id)
    {
        $d = $r->validate(['amount' => 'required|numeric|min:10|max:5000000', 'pan' => ['nullable', 'regex:' . self::PAN], 'address' => 'nullable|string|max:300', 'email' => 'nullable|email', 'anonymous' => 'nullable|boolean', 'donor_name' => 'nullable|string|max:120']);
        $c = DB::table('campaigns')->find($id) ?? abort(404);
        [$did, $p] = $this->svc->donate($r->user(), $c, $d);
        $e = DB::table('donation_entities')->find($c->entity_id);
        return ['donation_id' => $did, 'payment_no' => $p->number, 'eligible_80g' => $e ? $this->svc->is80g($e, $c->currency) : false, 'needs_pan_for_80g' => $e && $this->svc->is80g($e, $c->currency) && empty($d['pan']),
            'checkout' => $this->ledger->checkout($p, ['title' => $c->title])];
    }

    public function myDonations(Request $r)
    {
        return DB::table('campaign_donations as d')->join('campaigns as c', 'c.id', '=', 'd.campaign_id')->where('d.user_id', $r->user()->id)->orderByDesc('d.id')->limit(100)
            ->get(['d.id', 'c.title', 'd.amount', 'd.currency', 'd.status', 'd.paid_at', 'd.g80_no', 'd.pan_enc'])->map(fn ($x) => ['id' => $x->id, 'campaign' => $x->title, 'amount' => (float) $x->amount, 'currency' => $x->currency, 'status' => $x->status,
                'paid_at' => $x->paid_at, 'g80_no' => $x->g80_no, 'has_pan' => (bool) $x->pan_enc])->values();
    }

    /** Adds / corrects PAN after payment and issues the 80G certificate. */
    public function addPan(Request $r, int $id)
    {
        $d = $r->validate(['pan' => ['required', 'regex:' . self::PAN], 'address' => 'nullable|string|max:300']);
        $don = DB::table('campaign_donations')->where(['id' => $id, 'user_id' => $r->user()->id])->first() ?? abort(404);
        abort_if($don->g80_no, 422, 'The certificate has already been generated.');
        DB::table('campaign_donations')->where('id', $id)->update(['pan_enc' => Crypt::encryptString(strtoupper($d['pan'])), 'address' => $d['address'] ?? $don->address, 'updated_at' => now()]);
        $no = $don->status === 'paid' ? $this->svc->issue80g($id) : null;
        return ['ok' => true, 'g80_no' => $no, 'message' => $no ? '80G certificate generated.' : 'PAN saved. 80G is not applicable to this donation or the payment is pending.'];
    }

    public function certificate(Request $r, int $id)
    {
        $don = DB::table('campaign_donations')->where(['id' => $id, 'user_id' => $r->user()->id])->first() ?? abort(404);
        $file = $this->svc->absolute($don);
        abort_unless($file && is_file($file), 404, '80G certificate is not available yet.');
        return response()->download($file, "{$don->g80_no}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /* ---------------------------------------------------------------- relief / assistance */

    public function assistanceStore(Request $r)
    {
        $d = $r->validate(['kind' => 'required|in:medical,education,relief,other', 'title' => 'required|string|max:200', 'beneficiary' => 'nullable|string|max:150', 'details' => 'required|string|max:4000',
            'amount_needed' => 'nullable|numeric|min:0|max:100000000', 'documents' => 'nullable|array|max:10', 'documents.*' => 'url|max:300']);
        abort_if(DB::table('assistance_requests')->where('user_id', $r->user()->id)->whereIn('status', ['pending', 'verified'])->count() >= 3, 422, 'Your 3 requests are still under review.');
        $a = Area::of($r->user());
        $id = DB::table('assistance_requests')->insertGetId(array_merge($d, $a, ['amount_needed' => $d['amount_needed'] ?? 0, 'documents' => isset($d['documents']) ? json_encode($d['documents']) : null, 'user_id' => $r->user()->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
        UserNotification::notifyAdmins('New assistance request: ' . $d['title'], null, $a['pradesh']);
        return response()->json(['id' => $id, 'message' => 'Your request will be verified by the Mahasabha team.'], 201);
    }

    public function myAssistance(Request $r) { return DB::table('assistance_requests')->where('user_id', $r->user()->id)->orderByDesc('id')->get(); }

    /* ---------------------------------------------------------------- volunteers */

    public function volunteerStore(Request $r)
    {
        $d = $r->validate(['campaign_id' => 'nullable|integer', 'skills' => 'nullable|string|max:250', 'availability' => 'nullable|string|max:120', 'note' => 'nullable|string|max:250']);
        if (! empty($d['campaign_id'])) abort_unless(DB::table('campaigns')->where('id', $d['campaign_id'])->whereIn('status', ['live', 'completed'])->exists(), 404);
        return response()->json(['id' => $this->svc->volunteerRegister($r->user(), $d + ['campaign_id' => null]), 'message' => 'Registered as a volunteer. The team will contact you.'], 201);
    }

    public function myVolunteer(Request $r)
    {
        return DB::table('volunteers as v')->leftJoin('campaigns as c', 'c.id', '=', 'v.campaign_id')->where('v.user_id', $r->user()->id)->orderByDesc('v.id')->get(['v.id', 'v.skills', 'v.availability', 'v.hours_logged', 'v.status', 'c.title as campaign']);
    }
}
