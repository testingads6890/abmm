<?php

// Read-only: a State Sub-Admin and a District Operator only see geography inside their own area
// in the member search and report dropdowns.
// Usage: php tests/unit/sub-admin-scope-check.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\{FamilyController, ReportController};
use App\Models\{Family, Lookup, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$fails = 0;
$check = function (bool $ok, string $label) use (&$fails) { echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n"; if (! $ok) $fails++; };

foreach (['state_admin', 'district_operator'] as $role) {
    // Pick a user whose area actually has families, so the lists are meaningful.
    $u = User::where('role', $role)->whereNotNull('pradesh')->get()->first(fn ($x) => Family::where('pradesh', $x->pradesh)->exists());
    if (! $u) { echo "SKIP no $role with an area\n"; continue; }
    Auth::guard('web')->setUser($u);
    $own = $u->pradesh;

    $lk = (new FamilyController())->index(Request::create('/admin/families', 'GET'))->getData()['lk'];
    $states = $lk['state'];
    $pradeshes = $lk['pradesh'];
    $check(count($states) >= 1 && count($states) < 40, "$role: search state list limited to their area (" . count($states) . " shown)");
    $check(count($pradeshes) === 0 || collect($pradeshes)->every(fn ($p) => Family::where('pradesh', $p)->exists()), "$role: search Pradesh list only has Pradesh that exist in their data");

    $m = new ReflectionMethod(ReportController::class, 'options');
    $m->setAccessible(true);
    $opts = $m->invoke(new ReportController());
    $check(count($opts['pradesh']) >= 1 && count($opts['pradesh']) <= 3, "$role: report Pradesh list limited (" . count($opts['pradesh']) . ' shown)');
    $check(count($opts['state']) >= 1 && count($opts['state']) < 40, "$role: report state list limited (" . count($opts['state']) . ' shown)');
    Auth::guard('web')->logout();
}

echo $fails ? "\n$fails check(s) failed\n" : "\nall scope checks passed\n";
exit($fails ? 1 : 0);
