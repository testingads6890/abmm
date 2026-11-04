<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One place in the tree Country > State > District > City > Zone > Pincode. */
class GeoNode extends Model
{
    public const LEVELS = ['country' => 'Country', 'state' => 'State', 'district' => 'District', 'city' => 'City', 'zone' => 'Zone', 'pincode' => 'Pincode'];
    public const CHILD = ['country' => ['state'], 'state' => ['district'], 'district' => ['city'], 'city' => ['zone', 'pincode'], 'zone' => ['pincode'], 'pincode' => []];

    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }

    /** Names from country down to this node, keyed by level. */
    public function path(): array
    {
        $out = []; $n = $this;
        for ($i = 0; $n && $i < 8; $i++, $n = $n->parent) $out[$n->level] = $n->name;
        return array_reverse($out, true);
    }

    public function crumbs(): array
    {
        $out = []; $n = $this;
        for ($i = 0; $n && $i < 8; $i++, $n = $n->parent) array_unshift($out, $n);
        return $out;
    }
}
