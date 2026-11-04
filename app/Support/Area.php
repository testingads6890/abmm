<?php

namespace App\Support;

use App\Models\{Family, User};

/** Geography of a member (from the family record) in the column names every new module stores: country, pradesh, district, city, zone. */
class Area
{
    public static function of(User $u): array
    {
        $f = $u->family_id ? Family::withoutGlobalScopes()->find($u->family_id) : null;
        return ['family_id' => $f?->id, 'country' => $f?->country, 'pradesh' => $f?->pradesh ?: $f?->state, 'district' => $f?->district, 'city' => $f?->city, 'zone' => $f?->zone];
    }

    /** Same, without family_id (for tables that are not owned by one family). */
    public static function geo(User $u): array { return array_diff_key(self::of($u), ['family_id' => 1]); }

    /** Lower-case keyword tokens (3+ letters, no filler words) used by the matching helpers. */
    public static function tokens(?string $s): array
    {
        $stop = ['the', 'and', 'for', 'with', 'from', 'that', 'this', 'have', 'want', 'your', 'about', 'kaise', 'hai', 'mujhe', 'mera', 'meri', 'aur', 'ke', 'ka', 'ki', 'ko', 'me', 'mein', 'nahi', 'karna', 'chahiye', 'job', 'jobs'];
        $w = preg_split('/[^\p{L}\p{N}+#.]+/u', mb_strtolower((string) $s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter($w, fn ($x) => mb_strlen($x) >= 3 && ! in_array($x, $stop, true))));
    }
}
