<?php

// Read-only: Hanging Data lists the right members, and the archive view lists only archived members.
// Usage: php tests/unit/hanging-data-check.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\ReportController;
use App\Models\Member;
use Illuminate\Http\Request;

$ctl = new ReportController();
$build = new ReflectionMethod($ctl, 'build');
$build->setAccessible(true);
$fails = 0;
$check = function (bool $ok, string $label) use (&$fails) { echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n"; if (! $ok) $fails++; };

[$q] = $build->invoke($ctl, 'moved', Request::create('/admin/reports/moved', 'GET', []));
$rows = (clone $q)->limit(500)->get();
$check(true, 'hanging issues query runs (' . $rows->count() . ' rows)');
$bad = $rows->filter(fn ($m) => ! (
    ($m->is_head && $m->family && $m->family->head_name !== $m->name)
    || ($m->family && trim((string) $m->family->address) === '')
    || ($m->gender === 'Female' && $m->marital_status === 'Married' && $m->relation === 'DAUGHTER')
))->count();
$check($bad === 0, 'every listed member matches at least one Hanging Data rule');
$check($rows->every(fn ($m) => in_array($m->status, ['active', 'inactive'], true)), 'issue rows are not archived members');

[$a] = $build->invoke($ctl, 'moved', Request::create('/admin/reports/moved', 'GET', ['show' => 'archived']));
$arch = (clone $a)->limit(500)->get();
$check($arch->every(fn ($m) => $m->status === 'archived'), 'archived view lists only archived members (' . $arch->count() . ' rows)');

echo $fails ? "\n$fails check(s) failed\n" : "\nall checks passed\n";
exit($fails ? 1 : 0);
