<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Family, GeoNode, Zone};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Location master (Super Admin): Country > State > District > City > Zone 1, Zone 2 ... > Pincode. Country / City admins only work inside their own area. */
class LocationController extends Controller
{
    public function index(Request $r)
    {
        $node = $r->filled('node') ? GeoNode::findOrFail((int) $r->node) : null;
        if ($node) $this->guard($r, $node);
        $level = $node ? null : 'country';
        $q = GeoNode::query()->withCount('children');
        $node ? $q->where('parent_id', $node->id) : $q->whereNull('parent_id');
        $u = $r->user();
        if (! $u->isSuper()) {                                   // sub-admins only see their own branch of the tree
            abort_unless($u->hasArea(), 403);
            $mine = ['country' => $u->country ? strtoupper($u->country) : null, 'state' => $u->pradesh, 'district' => $u->district, 'city' => $u->city, 'zone' => $u->zone];
            $lv = $node ? (GeoNode::CHILD[$node->level][0] ?? null) : 'country';
            if ($lv && ! empty($mine[$lv])) $q->where('name', $mine[$lv]);
            if (! $node && empty($mine['country']) && $u->pradesh) $q->whereIn('id', GeoNode::where(['level' => 'state', 'name' => $u->pradesh])->select('parent_id'));
        }
        if ($r->filled('q')) $q->where('name', 'like', '%' . $r->q . '%');
        $rows = $q->orderBy('sort')->orderBy('name')->paginate(100)->withQueryString();
        $childLevels = $node ? GeoNode::CHILD[$node->level] : ['country'];
        if (! $r->user()->isSuper()) $childLevels = array_values(array_filter($childLevels, fn ($l) => $this->rank($l) >= $this->floor($r->user())));
        return view('admin.global.locations', ['node' => $node, 'rows' => $rows, 'crumbs' => $node?->crumbs() ?? [], 'childLevels' => $childLevels,
            'levels' => GeoNode::LEVELS, 'stats' => $node ? null : $this->stats()]);
    }

    private function stats(): array
    {
        return DB::table('geo_nodes')->select('level', DB::raw('count(*) as n'))->groupBy('level')->pluck('n', 'level')->all();
    }

    public function store(Request $r)
    {
        $parent = $r->filled('parent_id') ? GeoNode::findOrFail((int) $r->parent_id) : null;
        if ($parent) $this->guard($r, $parent); else abort_unless($r->user()->isSuper(), 403, 'Only the Super Admin can add a country.');
        $level = $r->validate(['level' => 'required|in:' . implode(',', array_keys(GeoNode::LEVELS))])['level'];
        abort_if(! $r->user()->isSuper() && $this->rank($level) < $this->floor($r->user()), 403, 'You do not have permission to add this level.');
        abort_unless($parent ? in_array($level, GeoNode::CHILD[$parent->level], true) : $level === 'country', 422, 'This cannot be added at this level.');
        $d = $r->validate(['name' => 'required|string|max:150', 'code' => 'nullable|string|max:150']);
        if ($level === 'pincode') $r->validate(['name' => ['regex:/^[A-Za-z0-9 -]{3,10}$/']], ['name.regex' => 'Pincode must be 3-10 letters/digits.']);
        $name = $level === 'country' ? strtoupper(trim($d['name'])) : trim($d['name']);
        $this->make($parent?->id, $level, $name, $d['code'] ?? null);
        return back()->with('ok', GeoNode::LEVELS[$level] . ' added.');
    }

    public function update(Request $r, GeoNode $node)
    {
        $this->guard($r, $node, true);
        $d = $r->validate(['name' => 'required|string|max:150', 'code' => 'nullable|string|max:150', 'sort' => 'nullable|integer|min:0|max:9999']);
        abort_if($node->level === 'country' && ! $r->user()->isSuper(), 403);
        $old = $node->name;
        $node->update(['name' => $node->level === 'country' ? strtoupper(trim($d['name'])) : trim($d['name']), 'code' => $d['code'] ?? null, 'sort' => $d['sort'] ?? $node->sort]);
        if ($node->level === 'zone' && $old !== $node->name) $this->syncZone($node, $old);
        return back()->with('ok', 'Updated.');
    }

    public function toggle(Request $r, GeoNode $node)
    {
        $this->guard($r, $node, true);
        $node->update(['is_active' => ! $node->is_active]);
        if ($node->level === 'zone') $this->syncZone($node);
        return back()->with('ok', 'Updated.');
    }

    public function destroy(Request $r, GeoNode $node)
    {
        $this->guard($r, $node, true);
        abort_if($node->children()->exists(), 422, 'Remove the items inside it first (or make it inactive).');
        $p = $node->path();
        $col = ['country' => 'country', 'state' => 'pradesh', 'district' => 'district', 'city' => 'city', 'zone' => 'zone'][$node->level] ?? null;
        if ($col && Family::withoutGlobalScopes()->where($col, $node->name)->when(isset($p['city']) && $node->level === 'zone', fn ($q) => $q->where('city', $p['city']))->exists())
            abort(422, 'Families are registered at this place - it cannot be deleted. Make it inactive instead.');
        if ($node->level === 'zone') Zone::where(['country' => $p['country'] ?? '', 'city' => $p['city'] ?? '', 'name' => $node->name])->delete();
        $node->delete();
        return back()->with('ok', 'Hata diya.');
    }

    /** Bulk: one per line  Country | State | District | City | Zone | Pincode | Area   (Zone, Pincode, Area optional; Zone "#5" => Zone 1..5; pincodes can be comma separated). */
    public function import(Request $r)
    {
        abort_unless($r->user()->can('geography.create'), 403);
        $r->validate(['bulk' => 'required|string|max:200000']);
        $n = 0; $skip = 0;
        foreach (preg_split('/\R/', $r->bulk) as $line) {
            if (trim($line) === '') continue;
            $p = array_map('trim', explode('|', $line)) + array_fill(0, 7, '');
            [$country, $state, $district, $city, $zone, $pins, $area] = $p;
            if ($country === '' || $state === '') { $skip++; continue; }
            $u = $r->user();
            if (! $u->isSuper() && (! $u->hasArea() || strcasecmp($u->country, $country) !== 0 || ($u->city && $city !== '' && strcasecmp($u->city, $city) !== 0))) { $skip++; continue; }
            $parent = null;
            foreach ([['country', strtoupper($country)], ['state', $state], ['district', $district], ['city', $city]] as [$lv, $nm]) {
                if ($nm === '') break;
                $before = DB::table('geo_nodes')->count(); $parent = $this->make($parent, $lv, $nm); $n += DB::table('geo_nodes')->count() - $before;
            }
            if ($city === '' || ! $parent) continue;
            $zoneNodes = [null];
            if ($zone !== '') { $zoneNodes = []; foreach (preg_match('/^#(\d{1,2})$/', $zone, $m) ? array_map(fn ($i) => "Zone $i", range(1, (int) $m[1])) : [$zone] as $i => $zn) { $b = DB::table('geo_nodes')->count(); $zoneNodes[] = $this->make($parent, 'zone', $zn, null, $i + 1); $n += DB::table('geo_nodes')->count() - $b; } }
            foreach (array_filter(array_map('trim', explode(',', $pins))) as $pin) {
                if (! preg_match('/^[A-Za-z0-9 -]{3,10}$/', $pin)) { $skip++; continue; }
                foreach ($zoneNodes as $zp) { $b = DB::table('geo_nodes')->count(); $this->make($zp ?? $parent, 'pincode', $pin, $area ?: null); $n += DB::table('geo_nodes')->count() - $b; }
            }
        }
        return back()->with('ok', "$n new items created" . ($skip ? ", $skip lines/pincodes skipped (wrong format or area)." : '.'));
    }

    /** Find-or-create a node. Zones are mirrored into the older `zones` table the rest of the app already reads. */
    private function make(?int $parent, string $level, string $name, ?string $code = null, int $sort = 0): int
    {
        $q = GeoNode::where(['level' => $level, 'name' => $name]);
        $parent === null ? $q->whereNull('parent_id') : $q->where('parent_id', $parent);
        $node = $q->first() ?: GeoNode::create(['parent_id' => $parent, 'level' => $level, 'name' => $name, 'code' => $code, 'sort' => $sort, 'is_active' => true]);
        if ($code && ! $node->code) $node->update(['code' => $code]);
        if ($level === 'zone') $this->syncZone($node);
        return $node->id;
    }

    private function syncZone(GeoNode $node, ?string $oldName = null): void
    {
        $p = $node->path();
        if (empty($p['city'])) return;
        $key = ['country' => $p['country'] ?? 'INDIA', 'city' => $p['city'], 'name' => $oldName ?? $node->name];
        Zone::updateOrCreate($key, ['name' => $node->name, 'state' => $p['state'] ?? null, 'district' => $p['district'] ?? null, 'sort' => $node->sort, 'is_active' => $node->is_active]);
    }

    private function rank(string $level): int { return array_search($level, array_keys(GeoNode::LEVELS), true); }

    /** Lowest level a role may add: State Sub-Admin => districts, District Admin => cities, City Admin => zones / pincodes. */
    private function floor(\App\Models\User $u): int
    {
        return match ($u->role) { 'country_admin' => $this->rank('state'), 'state_admin' => $this->rank('district'), 'district_operator' => $this->rank('city'), 'city_admin' => $this->rank('zone'), default => $this->rank('pincode') };
    }

    private function guard(Request $r, GeoNode $node, bool $modify = false): void
    {
        $u = $r->user();
        if ($u->isSuper()) return;
        abort_unless($u->hasArea(), 403);
        $p = $node->path();
        $mine = ['country' => $u->country ? strtoupper($u->country) : null, 'state' => $u->pradesh, 'district' => $u->district, 'city' => $u->city, 'zone' => $u->zone];
        foreach ($mine as $lv => $val) if ($val && isset($p[$lv]) && strcasecmp($val, $p[$lv]) !== 0) abort(403, 'This area is not yours.');
        abort_if($modify && $node->level !== 'country' && $this->rank($node->level) < $this->floor($u) , 403, 'You do not have permission to change this level.');
    }
}
