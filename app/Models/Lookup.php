<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lookup extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'meta' => 'array'];

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeOfType($q, string $type) { return $q->where('type', $type); }

    /** Active values per type, e.g. ['surname' => [...], 'tehsil' => [...]] (used by every dropdown in the panel). */
    public static function grouped(): array
    {
        return cache()->remember('lookups.grouped', 300, fn () => static::active()->orderBy('sort')->orderBy('name')->get(['type', 'name'])->groupBy('type')->map(fn ($g) => $g->pluck('name')->unique()->values()->all())->all());
    }

    public static function flush(): void { cache()->forget('api.masters'); cache()->forget('lookups.grouped'); cache()->forget('lookups.anchal_pradesh'); cache()->forget('lookups.tehsil_meta'); cache()->forget('lookups.caste_gotras'); cache()->forget('lookups.state_districts'); cache()->forget('lookups.country_codes'); foreach (['salary_range', 'job_salary_range'] as $b) cache()->forget("salary_bands.$b"); }

    /** caste name => [its gotras] (active only), for dependent Caste -> Gotra dropdowns. */
    /** state name => [district names] from Master Data (for dependent State -> District dropdowns). */
    public static function stateDistricts(): array
    {
        return cache()->remember('lookups.state_districts', 600, function () {
            $states = static::where('type', 'state')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
            $dist = static::where('type', 'district')->where('is_active', true)->orderBy('name')->get(['name', 'parent_id'])->groupBy('parent_id');
            return $states->unique('name')->mapWithKeys(fn ($st) => [$st->name => ($dist[$st->id] ?? collect())->pluck('name')->unique()->values()->all()])->all();
        });
    }

    public static function casteGotras(): array
    {
        return cache()->remember('lookups.caste_gotras', 300, function () {
            $castes = static::ofType('caste')->active()->orderBy('sort')->orderBy('name')->get(['id', 'name']);
            $gt = static::ofType('gotra')->active()->orderBy('sort')->orderBy('name')->get(['name', 'parent_id'])->groupBy('parent_id');
            return $castes->mapWithKeys(fn ($c) => [$c->name => ($gt[$c->id] ?? collect())->pluck('name')->unique()->values()->all()])->all();
        });
    }

    /** Anchal name => [ABMM Pradesh names under it] (active only). Used by the Anchal filter in reports and Anchal-wide notifications. */
    public static function anchalPradesh(): array
    {
        return cache()->remember('lookups.anchal_pradesh', 300, function () {
            $anchals = static::ofType('anchal')->active()->orderBy('sort')->orderBy('name')->get(['id', 'name']);
            $pr = static::ofType('pradesh')->active()->whereNotNull('parent_id')->orderBy('sort')->orderBy('name')->get(['name', 'parent_id'])->groupBy('parent_id');
            return $anchals->mapWithKeys(fn ($a) => [$a->name => ($pr[$a->id] ?? collect())->pluck('name')->unique()->values()->all()])->all();
        });
    }

    /** Pradesh names of one Anchal (case-insensitive Anchal name). Empty array when the Anchal is unknown. */
    public static function pradeshesOf(?string $anchal): array
    {
        foreach (static::anchalPradesh() as $a => $list) if (strcasecmp($a, trim((string) $anchal)) === 0) return $list;
        return [];
    }

    /**
     * District and Anchal for an ABMM Pradesh name. The Pradesh is either the one a tehsil carries, or the
     * "<STATE> PRADESH MAHESHWARI SABHA" name of a state, whose Anchal is then taken from the Pradesh the state is linked to.
     */
    public static function districtAnchalOf(string $pradesh): array
    {
        $meta = static::tehsilMeta();
        $district = null;
        $linked = null;
        foreach ($meta as $m) {
            if (($m['pradesh'] ?? null) === $pradesh) { $district ??= $m['district']; $linked ??= $m['pradesh']; }
        }
        if ($district === null) {
            foreach ($meta as $m) {
                if (($m['state'] ?? '') !== '' && str_starts_with($pradesh, $m['state'])) {
                    $district = $m['district'];
                    $linked = $m['pradesh'];
                    break;
                }
            }
        }
        return [
            'district' => $district ?? '',
            'anchal' => static::anchalOf($pradesh) ?? static::anchalOf($linked) ?? '',
        ];
    }

    /** Anchal of a Pradesh name, or null. */
    public static function anchalOf(?string $pradesh): ?string
    {
        foreach (static::anchalPradesh() as $a => $list) foreach ($list as $p) if (strcasecmp($p, trim((string) $pradesh)) === 0) return $a;
        return null;
    }

    /** country name => phone code ("INDIA" => "+91"), only countries that have a code. */
    public static function countryCodes(): array
    {
        return cache()->remember('lookups.country_codes', 300, fn () => static::ofType('country')->active()->orderBy('sort')->orderBy('name')->get(['name', 'meta'])
            ->filter(fn ($c) => ! empty($c->meta['dial']))->mapWithKeys(fn ($c) => [$c->name => $c->meta['dial']])->all());
    }

    /** tehsil name => ['district' => ..., 'state' => ..., 'pradesh' => ..., 'pincode' => ...] for dependent dropdown + pincode auto-fill. */
    public static function tehsilMeta(): array
    {
        return cache()->remember('lookups.tehsil_meta', 300, function () {
            $out = [];
            foreach (static::active()->ofType('tehsil')->with('parent.parent.parent')->get() as $t) {
                $d = $t->parent; $s = $d?->parent; $p = $s?->parent;
                $out[$t->name] = ['country' => $s?->meta['country'] ?? 'INDIA', 'district' => $d?->name, 'state' => $s?->name, 'pradesh' => $p?->name, 'pincode' => $t->pincode];
            }
            return $out;
        });
    }

    /** tehsilMeta limited to a user's own area (Super Admin: everything). */
    public static function tehsilMetaFor(?User $u): array
    {
        $all = static::tehsilMeta();
        if (! $u || $u->isSuper()) return $all;
        // A user's Pradesh is "<STATE> PRADESH MAHESHWARI SABHA" for most states, while the tehsil carries the Pradesh the state is linked to.
        // So a tehsil is in the user's area when its Pradesh is the user's, or its state name starts the user's Pradesh name.
        return array_filter($all, fn ($m, $name) => (($m['pradesh'] ?? null) === $u->pradesh
                || (($m['state'] ?? '') !== '' && str_starts_with((string) $u->pradesh, (string) $m['state'])))
            && (! $u->district || ($m['district'] ?? null) === $u->district) && (! $u->tehsil || $name === $u->tehsil), ARRAY_FILTER_USE_BOTH);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }
}
