<?php

// Read-only: every filter on the Member and Family reports must return only rows that match it.
// Usage: php tests/unit/report-filters-check.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\ReportController;
use App\Models\{Family, Member};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$ctl = new ReportController();
$build = new ReflectionMethod($ctl, 'build');
$build->setAccessible(true);
$fails = [];
$report = [];

$rowsFor = function (string $type, array $in) use ($ctl, $build) {
    [$q] = $build->invoke($ctl, $type, Request::create('/admin/reports/' . $type, 'GET', $in));
    return (clone $q)->limit(400)->get();
};
$check = function (string $label, string $type, array $in, callable $ok) use ($rowsFor, &$fails, &$report) {
    $rows = $rowsFor($type, $in);
    $bad = $rows->filter(fn ($r) => ! $ok($r))->count();
    $report[] = sprintf('%-30s rows=%-4d wrong=%d', "$type $label", $rows->count(), $bad);
    if ($bad) $fails[] = "$type $label returned $bad wrong row(s)";
};
$val = fn (string $t, string $c) => DB::table($t)->whereNotNull($c)->where($c, '!=', '')->where('status', 'active')->value($c);

// Member report (rows are Members; family-level fields come through the family).
$check('gender=Male', 'member', ['gender' => 'male'], fn ($m) => strcasecmp((string) $m->gender, 'Male') === 0);
foreach (['blood_group', 'marital_status', 'education', 'occupation', 'profession', 'business_type'] as $c) {
    $v = $val('members', $c);
    if ($v) $check("$c=$v", 'member', [$c => $v], fn ($m) => $m->{$c} === $v);
}
$check('letter=M', 'member', ['letter' => 'M'], fn ($m) => strtoupper(substr($m->surname, 0, 1)) === 'M');
$check('age 21-25', 'member', ['age_group' => '21-25'], fn ($m) => $m->dob && $m->dob->between(now()->subYears(26)->addDay(), now()->subYears(21)));
$check('q=prefix', 'member', ['q' => 'MA'], fn ($m) => stripos($m->surname . ' ' . $m->name . ' ' . $m->mobile, 'MA') !== false);
$v = $val('families', 'tehsil');
if ($v) $check("tehsil=$v", 'member', ['tehsil' => $v], fn ($m) => $m->family?->tehsil === $v);
$v = $val('families', 'state');
if ($v) $check("state=$v", 'member', ['state' => $v], fn ($m) => $m->family?->state === $v);
$v = $val('families', 'pradesh');
if ($v) $check("pradesh=$v", 'member', ['pradesh' => $v], fn ($m) => $m->family?->pradesh === $v);
$check('anchal=PASCHIMANCHAL', 'member', ['anchal' => 'PASCHIMANCHAL'], fn ($m) => in_array($m->family?->pradesh, App\Models\Lookup::pradeshesOf('PASCHIMANCHAL'), true));
$check('income', 'member', ['income' => $val('families', 'income_range')], fn ($m) => $m->family?->income_range === $val('families', 'income_range'));

// Family report.
$v = $val('families', 'tehsil');
if ($v) $check("tehsil=$v", 'family', ['tehsil' => $v], fn ($f) => $f->tehsil === $v);
$v = $val('families', 'state');
if ($v) $check("state=$v", 'family', ['state' => $v], fn ($f) => $f->state === $v);
$check('mediclaim=yes', 'family', ['mediclaim' => 'yes'], fn ($f) => (bool) $f->has_mediclaim);
$check('mediclaim=no', 'family', ['mediclaim' => 'no'], fn ($f) => ! (bool) $f->has_mediclaim);
$check('pakshik=yes', 'family', ['pakshik' => 'yes'], fn ($f) => (bool) $f->pakshik_member);
$check('resident_type=OWNED', 'family', ['resident_type' => 'OWNED'], fn ($f) => $f->resident_type === 'OWNED');
$check('letter=M', 'family', ['letter' => 'M'], fn ($f) => strtoupper(substr($f->surname, 0, 1)) === 'M');
$check('gotra=none-active', 'family', ['gotra' => 'Bhutada'], fn ($f) => $f->gotra === 'Bhutada');
$check('dates 2020-2030', 'family', ['from' => '2020-01-01', 'to' => '2030-12-31'], fn ($f) => $f->created_at >= '2020-01-01' && $f->created_at <= '2030-12-31 23:59:59');

// Business and Ads reports.
$check('business status=active', 'business', ['status' => 'active'], fn ($b) => $b->status === 'active');
$check('ads status=approved', 'ads', ['status' => 'approved'], fn ($a) => $a->status === 'approved');
$check('reviews status=pending', 'reviews', ['status' => 'pending'], fn ($x) => $x->status === 'pending');

echo implode("\n", $report), "\n";
if ($fails) { echo "\nFAILURES:\n- " . implode("\n- ", $fails) . "\n"; exit(1); }
echo "\nall report filters return only matching rows\n";
