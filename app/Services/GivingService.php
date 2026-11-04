<?php

namespace App\Services;

use App\Models\{Family, User, UserNotification};
use App\Services\Messaging\MessageService;
use App\Services\Payments\PaymentLedger;
use App\Support\Area;
use Illuminate\Support\Facades\{Crypt, DB};

/**
 * Milestone E - donation campaigns: target + progress, 80G certificates for eligible entities, utilisation reports,
 * relief / assistance requests and volunteer registration. Payments run through the unified ledger (purpose = campaign).
 */
class GivingService
{
    public function __construct(private PaymentLedger $ledger) {}

    private function tell(?int $uid, string $title, string $body): void
    {
        if (! $uid || ! ($u = User::withoutGlobalScopes()->find($uid))) return;
        try { app(GreetingService::class)->notify($u, $title, $body); } catch (\Throwable $e) { report($e); UserNotification::create(['user_id' => $uid, 'title' => "$title: $body"]); }
    }

    public function progress(object $c): array
    {
        $pct = $c->target_amount > 0 ? min(100, round($c->raised_amount * 100 / $c->target_amount, 1)) : null;
        $days = $c->ends_on ? max(0, (int) today()->diffInDays(\Carbon\Carbon::parse($c->ends_on), false)) : null;
        return ['target' => (float) $c->target_amount, 'raised' => (float) $c->raised_amount, 'percent' => $pct, 'donors' => (int) $c->donors_count, 'days_left' => $days];
    }

    /** True when donations to this campaign earn an 80G certificate (entity registered, valid today, rupee campaign). */
    public function is80g(object $entity, ?string $currency = 'INR'): bool
    {
        if (! $entity->has_80g || ($currency ?: 'INR') !== 'INR' || blank($entity->g80_number) || blank($entity->pan)) return false;
        $t = today()->toDateString();
        return (! $entity->g80_valid_from || $entity->g80_valid_from <= $t) && (! $entity->g80_valid_to || $entity->g80_valid_to >= $t);
    }

    /** @return array{0:int,1:\App\Models\Payment} */
    public function donate(User $u, object $c, array $d): array
    {
        abort_unless($c->status === 'live', 422, 'This campaign is not accepting donations right now.');
        abort_if($c->ends_on && \Carbon\Carbon::parse($c->ends_on)->endOfDay()->isPast(), 422, 'This campaign has ended.');
        $a = Area::of($u); $f = $a['family_id'] ? Family::withoutGlobalScopes()->find($a['family_id']) : null;
        $name = $d['donor_name'] ?? ($f?->head_name ?: $u->name);
        $id = DB::table('campaign_donations')->insertGetId(['campaign_id' => $c->id, 'user_id' => $u->id, 'family_id' => $a['family_id'], 'donor_name' => $name, 'donor_mobile' => $u->mobile, 'donor_email' => $d['email'] ?? $f?->email,
            'pan_enc' => ! empty($d['pan']) ? Crypt::encryptString(strtoupper($d['pan'])) : null, 'address' => $d['address'] ?? null, 'amount' => $d['amount'], 'currency' => $c->currency, 'anonymous' => (bool) ($d['anonymous'] ?? false),
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $p = $this->ledger->create(['purpose' => 'campaign', 'purpose_ref' => (string) $id, 'amount' => $d['amount'], 'currency' => $c->currency, 'country' => $a['country'], 'city' => $a['city'], 'user_id' => $u->id, 'family_id' => $a['family_id'],
            'payer_name' => $name, 'payer_email' => $d['email'] ?? $f?->email, 'payer_mobile' => $f ? PhoneService::e164($f->mobile, $f->country_code ?: '+91') : null, 'audience' => 'member', 'meta' => ['title' => $c->title]]);
        DB::table('campaign_donations')->where('id', $id)->update(['payment_id' => $p->id]);
        return [$id, $p];
    }

    /** Settle hook. */
    public function paid(int $id): void
    {
        $don = DB::table('campaign_donations')->find($id); if (! $don || $don->status === 'paid') return;
        DB::transaction(function () use ($don) {
            DB::table('campaign_donations')->where('id', $don->id)->update(['status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);
            DB::table('campaigns')->where('id', $don->campaign_id)->update(['raised_amount' => DB::raw('raised_amount + ' . (float) $don->amount), 'donors_count' => DB::raw('donors_count + 1'), 'updated_at' => now()]);
        });
        $c = DB::table('campaigns')->find($don->campaign_id);
        if ($c && $c->status === 'live' && $c->target_amount > 0 && $c->raised_amount >= $c->target_amount) {
            DB::table('campaigns')->where('id', $c->id)->update(['status' => 'completed', 'updated_at' => now()]);
            $this->tell($c->user_id, 'Campaign target reached!', $c->title);
        }
        $this->tell($don->user_id, 'Thank you for your donation', ($don->currency ?: 'INR') . ' ' . number_format($don->amount, 2) . ' - ' . ($c->title ?? ''));
        try { $this->issue80g($don->id); } catch (\Throwable $e) { report($e); }
    }

    public function refunded(int $id): void
    {
        $don = DB::table('campaign_donations')->find($id); if (! $don || $don->status !== 'paid') return;
        DB::table('campaign_donations')->where('id', $id)->update(['status' => 'refunded', 'updated_at' => now()]);
        $c = DB::table('campaigns')->find($don->campaign_id);
        if ($c) DB::table('campaigns')->where('id', $c->id)->update(['raised_amount' => max(0, $c->raised_amount - $don->amount), 'donors_count' => max(0, $c->donors_count - 1), 'updated_at' => now()]);
    }

    /** Issues (once) the 80G certificate when the entity is eligible and the donor gave a PAN. Returns the number or null. */
    public function issue80g(int $donationId): ?string
    {
        $don = DB::table('campaign_donations')->find($donationId);
        if (! $don || $don->status !== 'paid' || $don->g80_no || ! $don->pan_enc) return $don->g80_no ?? null;
        $c = DB::table('campaigns')->find($don->campaign_id); $e = $c ? DB::table('donation_entities')->find($c->entity_id) : null;
        if (! $e || ! $this->is80g($e, $don->currency)) return null;
        $no = null;
        DB::transaction(function () use ($don, &$no) {
            $seq = DB::table('campaign_donations')->whereNotNull('g80_no')->count() + 1;
            $fy = today()->month >= 4 ? today()->year : today()->year - 1;
            do { $no = sprintf('80G-%d-%06d', $fy, $seq++); } while (DB::table('campaign_donations')->where('g80_no', $no)->exists());
            DB::table('campaign_donations')->where('id', $don->id)->update(['g80_no' => $no, 'updated_at' => now()]);
        });
        $don = DB::table('campaign_donations')->find($donationId);
        $path = $this->render80g($don, $e, $c);
        DB::table('campaign_donations')->where('id', $donationId)->update(['g80_path' => $path]);
        $this->tell($don->user_id, '80G certificate taiyaar', "{$no} - download in the app.");
        if ($don->donor_email) app(MessageService::class)->email($don->donor_email, "80G donation certificate {$no} - {$e->name}", "Thank you. Your 80G certificate is attached.", storage_path('app/private/' . $path), "{$no}.pdf");
        return $no;
    }

    public function absolute(object $don): ?string { return $don->g80_path ? storage_path('app/private/' . $don->g80_path) : null; }

    public static function words(int $n): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $two = fn (int $x) => $x < 20 ? $ones[$x] : trim($tens[intdiv($x, 10)] . ' ' . $ones[$x % 10]);
        if ($n === 0) return 'Zero';
        $o = '';
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$div, $name]) {
            if ($n >= $div) { $q = intdiv($n, $div); $o .= ($q >= 100 && $div >= 1000 ? self::words($q) : $two($q)) . " $name "; $n %= $div; }
        }
        return trim($o . ($n ? $two($n) : ''));
    }

    private function render80g(object $don, object $e, object $c): string
    {
        abort_unless(class_exists(\Mpdf\Mpdf::class), 501, 'composer require mpdf/mpdf');
        $dir = storage_path('app/private/80g'); if (! is_dir($dir)) mkdir($dir, 0775, true);
        $tmp = storage_path('app/mpdf'); if (! is_dir($tmp)) mkdir($tmp, 0775, true);
        $pan = Crypt::decryptString($don->pan_enc); $amt = number_format($don->amount, 2);
        $rows = [['Certificate no.', $don->g80_no], ['Date', ($don->paid_at ? \Carbon\Carbon::parse($don->paid_at) : now())->format('d M Y')], ['Donor', $don->donor_name], ['Donor PAN', $pan], ['Donor address', $don->address ?: '-'],
            ['Amount received', "Rs. $amt"], ['In words', 'Rupees ' . self::words((int) floor($don->amount)) . ' only'], ['Towards', $c->title], ['Mode', 'Online (payment ref. ' . ($don->payment_id ? DB::table('payments')->where('id', $don->payment_id)->value('number') : '-') . ')']];
        $html = '<style>body{font-family:dejavusans;font-size:10pt;color:#222}td{padding:5px 6px;border-bottom:0.3pt solid #ddd}.h{color:#1b1f4b;font-size:15pt}</style><div class="h"><b>' . e($e->name) . '</b></div>'
            . '<div style="font-size:8.5pt;color:#555">' . e((string) $e->address) . '<br>PAN: ' . e((string) $e->pan) . ($e->reg_no ? ' | Reg. no.: ' . e($e->reg_no) : '') . '</div><hr>'
            . '<h3>DONATION RECEIPT - SECTION 80G</h3><table width="100%">';
        foreach ($rows as [$k, $v]) $html .= '<tr><td width="32%"><b>' . e($k) . '</b></td><td>' . e((string) $v) . '</td></tr>';
        $html .= '</table><p style="font-size:9pt">Approval under Section 80G(5): <b>' . e((string) $e->g80_number) . '</b>' . ($e->g80_valid_from || $e->g80_valid_to ? ' (valid ' . e((string) $e->g80_valid_from) . ' to ' . e((string) $e->g80_valid_to) . ')' : '')
            . '. This donation is eligible for deduction under Section 80G of the Income Tax Act, 1961, as applicable to the donor.</p><br><br><div style="text-align:right;font-size:9pt">Authorised signatory' . ($e->signatory ? ': ' . e($e->signatory) : '') . '<br>' . e($e->name) . '</div>'
            . '<p style="font-size:8pt;color:#666;margin-top:24px">Computer generated certificate.</p>';
        $m = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A5', 'tempDir' => $tmp, 'autoScriptToLang' => true, 'autoLangToFont' => true]);
        $m->SetTitle($don->g80_no); $m->WriteHTML($html);
        $path = "80g/{$don->g80_no}.pdf";
        $m->Output(storage_path("app/private/$path"), \Mpdf\Output\Destination::FILE);
        return $path;
    }

    /* ------------------------------------------------------------------ utilisation reports */

    public function reportPublished(int $reportId): int
    {
        $r = DB::table('utilisation_reports')->find($reportId); if (! $r || ! $r->published) return 0;
        $c = DB::table('campaigns')->find($r->campaign_id); $n = 0;
        foreach (DB::table('campaign_donations')->where(['campaign_id' => $r->campaign_id, 'status' => 'paid'])->whereNotNull('user_id')->distinct()->pluck('user_id') as $uid) { $this->tell($uid, 'Utilisation report', ($c->title ?? '') . ': ' . $r->title); $n++; }
        return $n;
    }

    /* ------------------------------------------------------------------ relief / assistance */

    public function makeCampaign(int $assistanceId, $by = null): ?int
    {
        $a = DB::table('assistance_requests')->find($assistanceId); if (! $a || $a->campaign_id) return $a->campaign_id ?? null;
        $entity = DB::table('donation_entities')->where('is_active', true)->orderByDesc('has_80g')->orderBy('id')->first();
        abort_unless($entity, 422, 'Create a donation entity first.');
        $cid = DB::table('campaigns')->insertGetId(['entity_id' => $entity->id, 'user_id' => $a->user_id, 'assistance_id' => $a->id, 'title' => $a->title, 'category' => in_array($a->kind, ['medical', 'education', 'relief'], true) ? $a->kind : 'other',
            'story' => $a->details, 'beneficiary' => $a->beneficiary, 'target_amount' => $a->amount_approved ?? $a->amount_needed, 'status' => 'draft', 'country' => $a->country, 'pradesh' => $a->pradesh, 'district' => $a->district, 'city' => $a->city, 'zone' => $a->zone,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('assistance_requests')->where('id', $a->id)->update(['campaign_id' => $cid, 'status' => 'approved', 'updated_at' => now()]);
        $this->tell($a->user_id, 'Assistance request approved', $a->title . ' - fund raising campaign is being created.');
        return $cid;
    }

    public function volunteerRegister(User $u, array $d): int
    {
        abort_if(DB::table('volunteers')->where('user_id', $u->id)->where('campaign_id', $d['campaign_id'] ?? null)->whereIn('status', ['pending', 'active'])->exists(), 422, 'You are already a volunteer.');
        return DB::table('volunteers')->insertGetId($d + Area::of($u) + ['user_id' => $u->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    }
}
