<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeoNode;
use Illuminate\Http\Request;

/** Public cascading dropdown feed for forms and the apps: GET /api/v1/geo (countries) or /api/v1/geo?parent_id=ID (its children). Only active places. */
class GeoController extends Controller
{
    public function children(Request $r)
    {
        $q = GeoNode::where('is_active', true);
        $r->filled('parent_id') ? $q->where('parent_id', (int) $r->parent_id) : $q->whereNull('parent_id');
        if ($r->filled('level')) $q->where('level', $r->level);
        return ['data' => $q->orderBy('sort')->orderBy('name')->limit(2000)->get(['id', 'level', 'name', 'code'])];
    }

    /** GET /api/v1/geo/pincode/{pin} => the city / state it belongs to, to auto-fill address forms. */
    public function pincode(string $pin)
    {
        $out = GeoNode::where(['level' => 'pincode', 'name' => $pin, 'is_active' => true])->limit(10)->get()->map(fn ($n) => ['pincode' => $n->name, 'area' => $n->code] + $n->path());
        return ['data' => $out];
    }
}
