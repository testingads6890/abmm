<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{CountrySetting, Lookup, Zone};
use Illuminate\Http\Request;

/** Country settings (currency / gateway / tax / language) and city-wise Zones (Zone 1, Zone 2 ...). */
class GeographyController extends Controller
{
    /** Country Admin / City Admin only ever see their own country (and city). */
    private function zoneQuery(Request $r)
    {
        $u = $r->user();
        $q = Zone::query();
        if (! $u->isSuper()) {
            abort_unless($u->hasArea(), 403);
            $q->where('country', $u->country)->when($u->isGlobalRole() && $u->city, fn ($x) => $x->where('city', $u->city));
        }
        return $q;
    }

    public function index(Request $r)
    {
        $zones = $this->zoneQuery($r)->when($r->country, fn ($q, $v) => $q->where('country', $v))->when($r->city, fn ($q, $v) => $q->where('city', 'like', "%$v%"))
            ->orderBy('country')->orderBy('city')->orderBy('sort')->orderBy('name')->paginate(50)->withQueryString();
        return view('admin.global.geography', [
            'zones' => $zones, 'countries' => $r->user()->isSuper() ? CountrySetting::orderBy('country')->get() : CountrySetting::where('country', $r->user()->country)->get(),
            'masterCountries' => Lookup::ofType('country')->active()->orderBy('name')->pluck('name'),
        ]);
    }

    public function countrySave(Request $r)
    {
        abort_unless($r->user()->isSuper(), 403, 'Only the Super Admin can change country settings.');
        $d = $r->validate([
            'country' => 'required|string|max:100', 'iso' => 'nullable|string|max:3', 'dial_code' => ['required', 'regex:/^\+\d{1,4}$/'], 'currency' => 'required|alpha|size:3',
            'gateway' => 'required|in:razorpay,stripe,manual', 'tax_name' => 'nullable|string|max:20', 'tax_rate' => 'required|numeric|min:0|max:50',
            'language' => 'required|string|max:5', 'timezone' => 'required|timezone',
        ]);
        $d['country'] = strtoupper(trim($d['country'])); $d['currency'] = strtoupper($d['currency']);
        CountrySetting::updateOrCreate(['country' => $d['country']], $d + ['is_active' => $r->boolean('is_active', true)]);
        cache()->forget('lookups.country_codes');
        return back()->with('ok', 'Country settings saved.');
    }

    public function countryToggle(Request $r, CountrySetting $country)
    {
        abort_unless($r->user()->isSuper(), 403);
        $country->update(['is_active' => ! $country->is_active]);
        return back()->with('ok', 'Updated.');
    }

    public function zoneStore(Request $r)
    {
        $d = $r->validate(['country' => 'required|string|max:100', 'state' => 'nullable|string|max:150', 'district' => 'nullable|string|max:150', 'city' => 'required|string|max:150', 'name' => 'required|string|max:100', 'code' => 'nullable|string|max:20']);
        $this->guardArea($r, $d['country'], $d['city']);
        Zone::updateOrCreate(['country' => strtoupper(trim($d['country'])), 'city' => trim($d['city']), 'name' => trim($d['name'])], ['state' => $d['state'] ?? null, 'district' => $d['district'] ?? null, 'code' => $d['code'] ?? null, 'is_active' => true]);
        return back()->with('ok', 'Zone added.');
    }

    /** Bulk: one per line "Country | State | District | City | Zone name". Also auto-creates "Zone 1..N" with "Country | State | District | City | #5". */
    public function zoneImport(Request $r)
    {
        $r->validate(['bulk' => 'required|string|max:100000']);
        $n = 0; $skip = 0;
        foreach (preg_split('/\R/', $r->bulk) as $line) {
            $p = array_map('trim', explode('|', $line));
            if (count($p) < 5 || $p[3] === '' || $p[4] === '') { if (trim($line) !== '') $skip++; continue; }
            [$country, $state, $district, $city, $zone] = $p;
            try { $this->guardArea($r, $country, $city); } catch (\Throwable) { $skip++; continue; }
            $names = preg_match('/^#(\d{1,2})$/', $zone, $m) ? array_map(fn ($i) => "Zone $i", range(1, (int) $m[1])) : [$zone];
            foreach ($names as $i => $name) { Zone::updateOrCreate(['country' => strtoupper($country), 'city' => $city, 'name' => $name], ['state' => $state ?: null, 'district' => $district ?: null, 'sort' => $i + 1, 'is_active' => true]); $n++; }
        }
        return back()->with('ok', "$n zones saved" . ($skip ? ", $skip lines skipped (wrong format or area)." : '.'));
    }

    public function zoneToggle(Request $r, Zone $zone)
    {
        $this->guardArea($r, $zone->country, $zone->city);
        $zone->update(['is_active' => ! $zone->is_active]);
        return back()->with('ok', 'Updated.');
    }

    public function zoneDestroy(Request $r, Zone $zone)
    {
        $this->guardArea($r, $zone->country, $zone->city);
        abort_if(\App\Models\Family::withoutGlobalScopes()->where('country', $zone->country)->where('city', $zone->city)->where('zone', $zone->name)->exists(), 422, 'There are families in this zone - move them to another zone first, or disable the zone.');
        $zone->delete();
        return back()->with('ok', 'Zone hata diya.');
    }

    private function guardArea(Request $r, string $country, string $city): void
    {
        $u = $r->user();
        if ($u->isSuper()) return;
        abort_unless($u->hasArea() && strcasecmp($u->country, $country) === 0 && (! $u->city || strcasecmp($u->city, $city) === 0), 403, 'This area is not yours.');
    }
}
