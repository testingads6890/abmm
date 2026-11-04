<?php

namespace App\Services;

use App\Models\Family;
use Illuminate\Database\Eloquent\Builder;

/** Targeting for notifications / messages: nation-wide, whole country, state, district, city or a city's zone. */
class AudienceService
{
    public const LEVELS = ['all' => 'Whole network (all countries)', 'country' => 'Country', 'state' => 'State', 'district' => 'District', 'city' => 'City', 'zone' => 'City zone'];

    /** @param array{level:string,country?:?string,state?:?string,district?:?string,city?:?string,zone?:?string} $t */
    public function families(array $t): Builder
    {
        $q = Family::query()->where('status', 'active');       // HasArea still limits non-super senders to their own area
        $level = $t['level'] ?? 'all';
        foreach (['country', 'state', 'district', 'city', 'zone'] as $col) {
            if ($level === 'all') break;
            if (! empty($t[$col])) $q->where($col, $t[$col]);
            if ($col === $level) break;
        }
        return $q;
    }

    public function count(array $t): int { return $this->families($t)->count(); }

    /** @return array<int,array{mobile:string,dial:string,name:string,family_id:int}> */
    public function recipients(array $t): array
    {
        return $this->families($t)->get(['id', 'head_name', 'mobile', 'country_code'])
            ->map(fn ($f) => ['mobile' => $f->mobile, 'dial' => $f->country_code ?: '+91', 'name' => $f->head_name, 'family_id' => $f->id])->all();
    }
}
