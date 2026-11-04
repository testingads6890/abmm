<?php

namespace App\Models\Concerns;

/**
 * Restricts rows to the logged-in user's area (pradesh / district / tehsil) and stamps the area on create.
 * Super Admin sees everything. Guests/CLI/queue (no user) are unfiltered so the public site & jobs keep working.
 * Non-super users WITHOUT an assigned area see NOTHING (fail-closed).
 * Model declares: protected static array $areaColumns = ['pradesh', 'district', 'tehsil'];
 */
trait HasArea
{
    /** config/permissions.php 'area_scoped' decides per model (original site: Family/Member are area-limited, matrimony/committee/business are national). */
    protected static function areaEnforced(): bool
    {
        return (bool) config('permissions.area_scoped.' . class_basename(static::class), true);
    }

    protected static function bootHasArea(): void
    {
        static::addGlobalScope('area', function ($q) {
            $u = auth()->user();
            if (! $u || $u->isSuper() || $u->role === 'member' || ! static::areaEnforced()) return; // member-app accounts are scoped by family_id in their own controllers
            $f = array_intersect_key($u->areaFilter(), array_flip(static::$areaColumns));
            if (! $f || ! $u->hasArea()) { $q->whereRaw('1 = 0'); return; }
            foreach ($f as $col => $val) $q->where($q->getModel()->getTable() . '.' . $col, $val);
        });

        static::creating(function ($m) {
            $u = auth()->user();
            if (! $u || $u->isSuper() || ! static::areaEnforced()) return;
            // Non-super users can only create inside their own area: force it.
            foreach (array_intersect_key($u->areaFilter(), array_flip(static::$areaColumns)) as $col => $val) $m->{$col} = $val;
        });
    }
}
