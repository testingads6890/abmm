<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lookup;
use App\Models\MatrimonyProfile;
use App\Support\SalaryBand;
use Illuminate\Http\Request;

class MatrimonyController extends Controller
{
    private const AGE_BANDS = ['21-25' => [21, 25], '26-30' => [26, 30], '31-40' => [31, 40], '41-50' => [41, 50], '51-60' => [51, 60], '61-70' => [61, 70], '71-80' => [71, 80], '81-90' => [81, 90], '91-100' => [91, 100]];
    public const STATUS_LABELS = ['registered' => 'Registered', 'paid' => 'Paid Members', 'inactive' => 'Inactive'];

    public function index(Request $r)
    {
        $archive = $r->routeIs('admin.matrimony.archive');
        $in = fn (string $k) => array_values(array_filter((array) $r->input($k), fn ($v) => $v !== null && $v !== ''));
        $q = MatrimonyProfile::query()->where('status', $archive ? '=' : '!=', 'archived');
        foreach (['state', 'district', 'qualification', 'degree', 'occupation_type', 'occupation', 'surname', 'status', 'marital_status', 'gotra', 'diet'] as $k) {
            if ($vals = $in($k)) {
                $blank = in_array('__blank', $vals, true);
                $vals = array_values(array_diff($vals, ['__blank']));
                $q->where(fn ($w) => $w->whereIn($k, $vals)->when($blank, fn ($x) => $x->orWhereNull($k)->orWhere($k, '')));
            }
        }
        $band = collect(SalaryBand::bands('salary_range'))->firstWhere('label', $r->input('band'));
        $q->when($band, fn ($x) => $x->whereNotNull('annual_income')->where('annual_income', '>=', $band['min'])->when($band['max'] !== null, fn ($y) => $y->where('annual_income', '<=', $band['max'])))
            ->when($r->input('manglik') === 'yes', fn ($x) => $x->where('manglik', true))->when($r->input('manglik') === 'no', fn ($x) => $x->where('manglik', false))
            ->when($r->filled('height_min'), fn ($x) => $x->where('height_cm', '>=', (int) $r->input('height_min')))->when($r->filled('height_max'), fn ($x) => $x->where('height_cm', '<=', (int) $r->input('height_max')))
            ->when($r->filled('city'), fn ($x) => $x->where('city', 'like', str_replace(['%', '_'], ['\\%', '\\_'], trim($r->input('city'))) . '%'))
            ->when(in_array($r->input('visible'), ['0', '1'], true), fn ($x) => $x->where('visible', $r->input('visible') === '1'));
        if ($g = $in('gender')) $q->whereIn('gender', array_map(fn ($x) => ucfirst(strtolower($x)), $g));
        $q->when($r->from && $r->to, fn ($q) => $q->whereBetween('registered_at', [$r->from . ' 00:00:00', $r->to . ' 23:59:59']))
            ->when($r->letter, fn ($q, $v) => $q->where('name', 'like', "$v%"))
            ->when($r->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('mobile', 'like', "%$t%")->orWhere('parinay_id', $t)));
        $bandsSel = array_filter($in('age'), fn ($b) => isset(self::AGE_BANDS[$b]));
        if ($bandsSel) {
            $q->where(function ($w) use ($bandsSel) {
                foreach ($bandsSel as $b) {
                    [$lo, $hi] = self::AGE_BANDS[$b];
                    $w->orWhereBetween('dob', [now()->subYears($hi + 1)->addDay(), now()->subYears($lo)]);
                }
            });
        }
        $per = in_array((int) $r->per, [10, 25, 50, 100, 250]) ? (int) $r->per : 100;
        return view('admin.matrimony.index', ['rows' => $q->latest('registered_at')->paginate($per)->withQueryString(), 'statusLabels' => self::STATUS_LABELS, 'archive' => $archive, 'bands' => array_keys(self::AGE_BANDS), 'lk' => Lookup::grouped(), 'incomeBands' => SalaryBand::bands('salary_range'), 'sd' => Lookup::stateDistricts()]);
    }

    public function store(Request $r)
    {
        $d = $r->validate(['name' => 'required|max:120', 'gender' => ['required', fn ($a, $v, $f) => in_array(strtolower($v), ['male', 'female']) ?: $f('Invalid gender')], 'dob' => 'required|date|before:-18 years', 'qualification' => 'nullable|max:120', 'degree' => 'nullable|max:120', 'surname' => 'nullable|max:80', 'member_code' => 'nullable|max:12', 'occupation_type' => 'nullable|max:60', 'occupation' => 'nullable|max:120', 'height' => 'nullable|max:20', 'manglik' => 'boolean', 'mobile' => 'required|digits:10', 'address' => 'nullable|max:400', 'state' => 'nullable|max:80', 'district' => 'nullable|max:80', 'pincode' => 'nullable|digits:6', 'family_id' => 'nullable|exists:families,id', 'marital_status' => 'nullable|max:20', 'gotra' => ['nullable', 'max:80', \App\Support\MemberApp::inMaster('gotra')], 'height_cm' => 'nullable|integer|min:100|max:230', 'income_band' => 'nullable|max:60', 'diet' => 'nullable|max:20', 'city' => 'nullable|max:100', 'country' => 'nullable|max:60', 'visible' => 'boolean', 'about' => 'nullable|max:1000']);
        $d = $this->incomeFromBand($d);
        do { $pid = (string) random_int(1000000000, 9999999999); } while (MatrimonyProfile::where('parinay_id', $pid)->exists());
        MatrimonyProfile::create($d + ['extra' => \App\Support\CustomForm::collect($r, 'matrimony'), 'parinay_id' => $pid, 'registered_at' => now(), 'status' => 'registered']);
        return back()->with('ok', "Profile registered with Parinay ID $pid.");
    }

    /** income_band (label) -> annual_income (middle of the band; same rule as the app) and height_cm -> height text. */
    private function incomeFromBand(array $d): array
    {
        if (array_key_exists('income_band', $d)) {
            $hit = collect(SalaryBand::bands('salary_range'))->firstWhere('label', $d['income_band']);
            $d['annual_income'] = $hit ? (int) ($hit['max'] === null ? $hit['min'] * 1.25 : ($hit['min'] === 0 ? $hit['max'] / 2 : ($hit['min'] + $hit['max']) / 2)) : null;
            unset($d['income_band']);
        }
        if (! empty($d['height_cm']) && empty($d['height'])) { $in = (int) round($d['height_cm'] / 2.54); $d['height'] = intdiv($in, 12) . "'" . ($in % 12) . '"'; }
        return $d;
    }

    public function edit(MatrimonyProfile $profile)
    {
        return view('admin.matrimony.edit', ['p' => $profile, 'lk' => Lookup::grouped(), 'incomeBands' => SalaryBand::bands('salary_range')]);
    }

    public function update(Request $r, MatrimonyProfile $profile)
    {
        $x = \App\Support\CustomForm::collect($r, 'matrimony', $profile->extra);
        $profile->update(['extra' => $x] + $this->incomeFromBand($r->validate(['name' => 'required|max:120', 'surname' => 'nullable|max:80', 'gender' => ['required', fn ($a, $v, $f) => in_array(strtolower($v), ['male', 'female']) ?: $f('Invalid gender')], 'dob' => 'required|date|before:-18 years', 'qualification' => 'nullable|max:120', 'degree' => 'nullable|max:120', 'occupation_type' => 'nullable|max:60', 'occupation' => 'nullable|max:120', 'height' => 'nullable|max:20', 'manglik' => 'boolean', 'mobile' => 'required|digits:10', 'address' => 'nullable|max:400', 'state' => 'nullable|max:80', 'district' => 'nullable|max:80', 'pincode' => 'nullable|digits:6', 'marital_status' => 'nullable|max:20', 'gotra' => ['nullable', 'max:80', \App\Support\MemberApp::inMaster('gotra')], 'height_cm' => 'nullable|integer|min:100|max:230', 'income_band' => 'nullable|max:60', 'diet' => 'nullable|max:20', 'city' => 'nullable|max:100', 'country' => 'nullable|max:60', 'visible' => 'boolean', 'about' => 'nullable|max:1000', 'payment_ref' => 'nullable|max:60'])));
        return redirect()->route('admin.matrimony.index')->with('ok', 'Profile updated.');
    }

    public function status(Request $r, MatrimonyProfile $profile)
    {
        if ($r->input('status') === 'archived') $profile->archived_at = now();
        $profile->update($r->validate(['status' => 'required|in:registered,paid,inactive,archived', 'payment_ref' => 'nullable|max:60']));
        return back()->with('ok', 'Status badla.');
    }
}
