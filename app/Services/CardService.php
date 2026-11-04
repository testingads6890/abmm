<?php

namespace App\Services;

use App\Models\{Family, Plan, Subscription};
use Illuminate\Support\Facades\DB;

/** Digital member card: signed QR token, live status from the member subscription, public verification. */
class CardService
{
    public static function sign(string $kind, int $id): string { return substr(hash_hmac('sha256', "$kind|$id", (string) config('app.key')), 0, 10); }

    public static function token(int $familyId): string { return "ABMM-C-$familyId-" . self::sign('card', $familyId); }

    /** @return Family|null null when the token is malformed or forged */
    public function resolve(string $token): ?Family
    {
        if (! preg_match('/^ABMM-C-(\d+)-([a-f0-9]{10})$/', trim($token), $m) || ! hash_equals(self::sign('card', (int) $m[1]), $m[2])) return null;
        return Family::withoutGlobalScopes()->find((int) $m[1]);
    }

    /** active | expiring | expired | unverified. Membership without any paid member plan configured counts as active (free membership). */
    public function status(Family $f): array
    {
        $sub = Subscription::where('subscriber_type', 'member')->where('family_id', $f->id)->where('status', 'active')->orderByDesc('ends_on')->first();
        $until = $sub?->ends_on;
        $paidModel = Plan::active()->where('audience', 'member')->where('price', '>', 0)->exists();
        if ($f->verification_status !== 'verified' || ! $f->membership_no) $st = 'unverified';
        elseif (! $paidModel) $st = 'active';
        elseif (! $until && ! $sub) $st = 'expired';
        elseif ($until && $until->lt(today())) $st = 'expired';
        elseif ($until && $until->lte(today()->addDays(30))) $st = 'expiring';
        else $st = 'active';
        return ['status' => $st, 'valid_till' => $until?->toDateString()];
    }

    public function card(Family $f): array
    {
        $cat = DB::table('membership_categories')->where('code', $f->member_category ?: 'general')->first();
        return ['name' => trim("$f->head_name $f->surname"), 'membership_no' => $f->membership_no, 'category' => $cat->name ?? ucfirst((string) $f->member_category), 'category_code' => $cat->code ?? $f->member_category,
            'color' => $cat->color ?? '#B8860B', 'perks' => $cat->perks ?? null, 'chapter' => $f->chapter, 'city' => $f->city, 'country' => $f->country, 'photo_url' => \App\Support\MemberApp::photoUrl($f->photo),
            'qr' => self::token($f->id), 'verify_url' => url('/verify/card/' . self::token($f->id)), 'renew_plan_id' => $cat->plan_id ?? null] + $this->status($f);
    }
}
