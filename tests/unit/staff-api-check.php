<?php

// Read-only: the staff API endpoints return data without errors, as a Super Admin (controller level).
// Usage: php tests/unit/staff-api-check.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\V1\{FamilyController, ReportApiController};
use App\Models\{Family, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$user = User::where('role', 'super_admin')->firstOrFail();
Auth::setUser($user);
$fails = 0;
$check = function (string $label, callable $fn) use (&$fails) {
    try {
        $res = $fn();
        echo "PASS $label" . (is_string($res) ? " ($res)" : '') . "\n";
    } catch (\Throwable $e) {
        $fails++;
        echo "FAIL $label: " . substr($e->getMessage(), 0, 160) . "\n";
    }
};

$fam = new FamilyController();
$check('family search by text', fn () => count($fam->index(Request::create('/x', 'GET', ['q' => 'MAH']))['data']) . ' rows');
$check('family search by letter', fn () => count($fam->index(Request::create('/x', 'GET', ['letter' => 'M']))['data']) . ' rows');
$check('family search by state', fn () => count($fam->index(Request::create('/x', 'GET', ['state' => 'RAJASTHAN']))['data']) . ' rows');
$check('family search by gender (person level)', fn () => count($fam->index(Request::create('/x', 'GET', ['gender' => 'male']))['data']) . ' rows');
$check('family search by anchal', fn () => count($fam->index(Request::create('/x', 'GET', ['anchal' => 'PASCHIMANCHAL']))['data']) . ' rows');
$check('family search with no term returns a hint', fn () => isset($fam->index(Request::create('/x', 'GET', []))['hint']) ? 'hint' : throw new \RuntimeException('no hint'));
$f = Family::where('status', 'active')->first();
$check('family detail', fn () => count($fam->show($f)['family']) . ' fields, ' . count($fam->show($f)['members']) . ' members');
$check('family members list', fn () => count($fam->members($f)['members']) . ' members');

$rep = new ReportApiController();
foreach (['member', 'family', 'moved', 'business', 'committee'] as $type) {
    $check("report json $type", fn () => $rep->index(Request::create('/x', 'GET', ['per_page' => 10]), $type)['total'] . ' rows');
}

echo $fails ? "\n$fails check(s) failed\n" : "\nall staff API checks passed\n";
exit($fails ? 1 : 0);
