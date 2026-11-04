<?php

// Read-only: every filter on the member search page must return only families that really match it.
// Usage: php tests/unit/family-filters-check.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\FamilyController;
use App\Models\{Family, Member};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$ctl = new FamilyController();
$run = fn (array $in) => $ctl->index(Request::create('/admin/families', 'GET', $in + ['per' => 250]))->getData()['families'];
$fails = [];
$report = [];

// A family matches a person-level value when the family or any active member has it.
$personHas = fn (Family $f, string $col, $val) => ($f->{$col} ?? null) == $val
    || $f->members()->withoutGlobalScopes()->where('status', 'active')->where($col, $val)->exists();

$check = function (string $label, array $input, callable $ok) use ($run, &$fails, &$report) {
    $rows = $run($input);
    $total = $rows->total();
    $bad = 0;
    foreach ($rows->getCollection() as $f) { if (! $ok($f)) $bad++; }
    $report[] = sprintf('%-22s rows=%-4d wrong=%d', $label, $total, $bad);
    if ($bad) $fails[] = "$label returned $bad wrong row(s)";
};

// Pick real values from the data so the filters are tested with values that exist.
// Values come from ACTIVE records, because the search shows active families by default.
$val = fn (string $table, string $col) => DB::table($table)->whereNotNull($col)->where($col, '!=', '')
    ->when($table === 'families', fn ($q) => $q->where('status', 'active'))
    ->when($table === 'members', fn ($q) => $q->where('status', 'active'))->value($col);

foreach (['district', 'tehsil', 'state', 'city', 'pradesh', 'caste', 'pincode'] as $c) {
    $v = $val('families', $c);
    if ($v === null) { $report[] = "$c: no data"; continue; }
    $check("$c=$v", [$c => [$v]], fn ($f) => (string) $f->{$c} === (string) $v);
}
foreach (['gotra', 'blood_group', 'education', 'occupation', 'profession', 'business_type', 'marital_status'] as $c) {
    $v = $val('members', $c) ?? $val('families', $c);
    if ($v === null) { $report[] = "$c: no data"; continue; }
    $check("$c=$v", [$c => [$v]], fn ($f) => $personHas($f, $c, $v));
}
$check('gender=Male', ['gender' => ['Male']], fn ($f) => $personHas($f, 'gender', 'Male'));
$check('surname_text=MA', ['surname_text' => 'MA'], fn ($f) => stripos((string) $f->surname, 'MA') !== false);
$check('letter=M', ['letter' => 'M'], fn ($f) => strtoupper(substr($f->surname, 0, 1)) === 'M');
$check('status=inactive', ['status' => 'inactive'], fn ($f) => $f->status === 'inactive');
$check('has_mediclaim=yes', ['has_mediclaim' => 'yes'], fn ($f) => (bool) $f->has_mediclaim === true);
$check('has_mediclaim=no', ['has_mediclaim' => 'no'], fn ($f) => (bool) $f->has_mediclaim === false);
$check('pakshik_member=yes', ['pakshik_member' => 'yes'], fn ($f) => (bool) $f->pakshik_member === true);
$check('income_range', ['income_range' => $val('families', 'income_range')], fn ($f) => $f->income_range === $val('families', 'income_range'));
$check('resident_type=OWNED', ['resident_type' => 'OWNED'], fn ($f) => $f->resident_type === 'OWNED');
$check('pradesh_avail=yes', ['pradesh_avail' => ['yes']], fn ($f) => ! empty($f->pradesh));
$check('pradesh_avail=no', ['pradesh_avail' => ['no']], fn ($f) => empty($f->pradesh));
$check('anchal=PURVANCHAL', ['anchal' => 'PURVANCHAL'], fn ($f) => in_array($f->pradesh, App\Models\Lookup::pradeshesOf('PURVANCHAL'), true));
$check('age 20-40', ['age_min' => 20, 'age_max' => 40], function ($f) {
    return $f->members()->withoutGlobalScopes()->where('status', 'active')->whereNotNull('dob')
        ->whereBetween('dob', [now()->subYears(41)->addDay()->toDateString(), now()->subYears(20)->toDateString()])->exists();
});
$check('dob_month=3', ['dob_month' => 3], fn ($f) => $f->members()->withoutGlobalScopes()->where('status', 'active')->whereMonth('dob', 3)->exists());
$check('reg 2020-2030', ['reg_from' => '2020-01-01', 'reg_to' => '2030-12-31'], fn ($f) => $f->created_at >= '2020-01-01' && $f->created_at <= '2030-12-31 23:59:59');
$q = mb_substr((string) $val('families', 'surname'), 0, 2);
$check('q=prefix', ['q' => $q], fn ($f) => stripos((string) $f->surname . ' ' . $f->head_name . ' ' . $f->mobile . ' ' . $f->family_code, $q) !== false
    || $f->members()->withoutGlobalScopes()->where('status', 'active')->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('surname', 'like', "$q%"))->exists());

echo implode("\n", $report), "\n";
if ($fails) { echo "\nFAILURES:\n- " . implode("\n- ", $fails) . "\n"; exit(1); }
echo "\nall family filters return only matching rows\n";
