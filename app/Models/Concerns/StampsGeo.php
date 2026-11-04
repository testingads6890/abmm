<?php

namespace App\Models\Concerns;

use App\Models\{Family, User};

/** Copies country / city / zone from the owner's family onto member-generated rows (jobs, help requests, news, job profiles). */
trait StampsGeo
{
    protected static function bootStampsGeo(): void
    {
        static::saving(function ($m) {
            if ($m->country && $m->city) return;
            $fid = $m->family_id ?? ($m->user_id ? User::withoutGlobalScopes()->whereKey($m->user_id)->value('family_id') : null);
            if (! $fid) return;
            $f = Family::withoutGlobalScopes()->find($fid, ['country', 'city', 'zone']);
            if (! $f) return;
            foreach (['country', 'city', 'zone'] as $c) if (empty($m->{$c})) $m->{$c} = $f->{$c};
        });
    }
}
