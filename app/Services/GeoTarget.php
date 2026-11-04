<?php

namespace App\Services;

use App\Models\{Family, User};

/** Audience description {country,pradesh,state,district,city,zone}: empty = everyone. Used by polls and surveys. */
class GeoTarget
{
    public const KEYS = ['country', 'pradesh', 'state', 'district', 'city', 'zone'];

    public static function clean(?array $t): array
    {
        return array_filter(array_intersect_key($t ?? [], array_flip(self::KEYS)), fn ($v) => $v !== null && trim((string) $v) !== '');
    }

    /** A non-super creator can only target inside his own area: his area values always win. */
    public static function enforce(User $u, ?array $t): array
    {
        $t = self::clean($t);
        if ($u->isSuper()) return $t;
        abort_unless($u->hasArea(), 403);
        foreach ($u->areaFilter() as $k => $v) if (in_array($k, self::KEYS, true)) $t[$k] = $v;
        return $t;
    }

    public static function matches(array $target, Family $f): bool
    {
        foreach (self::clean($target) as $k => $v) if (mb_strtolower(trim((string) $f->{$k})) !== mb_strtolower(trim((string) $v))) return false;
        return true;
    }

    public static function label(?array $t): string
    {
        $t = self::clean($t);
        return $t ? implode(' › ', $t) : 'Everyone';
    }
}
