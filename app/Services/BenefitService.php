<?php

namespace App\Services;

use App\Models\{Family, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Vouchers / member benefits: eligibility rules, claim (unique code), redemption by the partner or admin. */
class BenefitService
{
    public function __construct(private CardService $cards, private PointsService $points) {}

    /** @return string|null reason it is NOT available, null when the member can claim it */
    public function blocker(object $b, User $u, Family $f): ?string
    {
        if (! $b->is_active) return 'The offer is closed.';
        if (($b->valid_from && $b->valid_from > today()->toDateString()) || ($b->valid_to && $b->valid_to < today()->toDateString())) return 'The offer has expired or has not started yet.';
        $e = json_decode($b->eligibility ?: '[]', true) ?: [];
        if (! empty($e['categories']) && ! in_array($f->member_category ?: 'general', $e['categories'], true)) return 'Your membership category is not eligible for this offer.';
        if (! empty($e['countries']) && ! in_array(mb_strtolower((string) $f->country), array_map('mb_strtolower', $e['countries']), true)) return 'This offer is not available in your country.';
        if (! empty($e['cities']) && ! in_array(mb_strtolower((string) $f->city), array_map('mb_strtolower', $e['cities']), true)) return 'This offer is not available in your city.';
        if (! empty($e['verified_only']) && $f->verification_status !== 'verified') return 'Please get your membership verified first.';
        if (! empty($e['active_only']) && $this->cards->status($f)['status'] === 'expired') return 'Please renew your membership.';
        if (! empty($e['min_points']) && $this->points->balance($u->id) < (int) $e['min_points']) return "This offer needs {$e['min_points']} points.";
        if ($b->total_limit && $b->claimed_count >= $b->total_limit) return 'Offers have ended.';
        if (DB::table('benefit_claims')->where(['benefit_id' => $b->id, 'user_id' => $u->id])->where('status', '!=', 'void')->count() >= $b->per_member_limit) return 'You have already claimed this offer.';
        return null;
    }

    public function claim(object $b, User $u, Family $f): array
    {
        return DB::transaction(function () use ($b, $u, $f) {
            $fresh = DB::table('benefits')->where('id', $b->id)->lockForUpdate()->first();
            if ($why = $this->blocker($fresh, $u, $f)) return ['error', $why];
            do { $code = strtoupper(Str::random(8)); } while (DB::table('benefit_claims')->where('code', $code)->exists());
            DB::table('benefit_claims')->insert(['benefit_id' => $b->id, 'user_id' => $u->id, 'family_id' => $f->id, 'country' => $f->country, 'city' => $f->city, 'code' => $code, 'status' => 'claimed', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('benefits')->where('id', $b->id)->increment('claimed_count');
            return ['ok', $code];
        });
    }

    /** Partner / admin enters or scans the member's code. */
    public function redeem(string $code, int $by): array
    {
        $c = DB::table('benefit_claims')->where('code', strtoupper(trim($code)))->first();
        if (! $c) return ['invalid', null];
        if ($c->status === 'redeemed') return ['duplicate', $c];
        if ($c->status !== 'claimed') return ['void', $c];
        DB::table('benefit_claims')->where('id', $c->id)->update(['status' => 'redeemed', 'redeemed_at' => now(), 'redeemed_by' => $by, 'updated_at' => now()]);
        return ['ok', DB::table('benefit_claims')->find($c->id)];
    }
}
