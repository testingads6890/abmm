<?php

// Read-only smoke test: loads every admin GET page as each role and reports server errors.
// Pages whose URL looks like an action (send, run, erase, sync, ...) are skipped so nothing is changed.
// Usage: php tests/unit/admin-smoke-check.php [role ...]   (default: super_admin state_admin district_operator)

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Family, Member, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$roles = array_slice($argv, 1) ?: ['super_admin', 'state_admin', 'district_operator'];
$skip = '/(send|run|erase|backup|reconcile|sync|logout|delete|destroy|restore|toggle|export-all|test|draw|recalc|rebuild|generate|seed|purge)/i';
$routes = [];
foreach ($app['router']->getRoutes() as $route) {
    if (! in_array('GET', $route->methods(), true)) continue;
    $uri = $route->uri();
    if (! str_starts_with($uri, 'admin')) continue;
    if (preg_match($skip, $uri . ' ' . $route->getName())) continue;
    $routes[] = $route;
}

// Real ids for route parameters, so detail pages are tested with data that exists.
$params = fn (string $name) => match ($name) {
    'family' => Family::value('id'), 'member' => Member::value('id'),
    default => null,
};

$results = [];
foreach ($roles as $role) {
    $user = User::where('role', $role)->first();
    if (! $user) { echo "no user with role $role\n"; continue; }
    // Sub-admins are forced to change their initial password first; simulate a changed password in memory only (nothing is saved).
    $user->must_change_password = false;
    Auth::guard('web')->setUser($user);
    foreach ($routes as $route) {
        $uri = $route->uri();
        $missing = false;
        foreach ($route->parameterNames() as $p) {
            $v = $params($p);
            if ($v === null) { $missing = true; break; }
            $uri = str_replace('{' . $p . '}', (string) $v, $uri);
            $uri = preg_replace('/\{' . preg_quote($p, '/') . '\?\}/', (string) $v, $uri);
        }
        if ($missing || str_contains($uri, '{')) { $results[$role]['skipped'][] = $route->uri(); continue; }
        try {
            $resp = $kernel->handle(Request::create('/' . $uri, 'GET'));
            $code = $resp->getStatusCode();
        } catch (\Throwable $e) {
            $code = 'EXC ' . substr($e->getMessage(), 0, 120);
        }
        $results[$role]['codes'][$uri] = $code;
        $kernel->terminate(Request::create('/' . $uri, 'GET'), $resp ?? null);
    }
    Auth::guard('web')->logout();
}

foreach ($results as $role => $r) {
    echo "\n== $role ==\n";
    $codes = $r['codes'] ?? [];
    $counts = array_count_values(array_map(fn ($c) => is_int($c) ? (string) $c : 'exception', $codes));
    ksort($counts);
    echo 'status counts: ' . json_encode($counts) . "\n";
    foreach ($codes as $uri => $c) {
        if ($c !== 200) echo "  NOT200 $uri => $c\n";
    }
    echo '  skipped (needs an id or an action): ' . count($r['skipped'] ?? []) . "\n";
}

// Fail when any page answered with a server error or an exception.
$serverErrors = 0;
foreach ($results as $r) {
    foreach (($r['codes'] ?? []) as $c) {
        if (! is_int($c) || $c >= 500) $serverErrors++;
    }
}
echo "\nserver errors: $serverErrors\n";
exit($serverErrors ? 1 : 0);
