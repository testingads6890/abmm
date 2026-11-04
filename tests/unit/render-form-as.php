<?php

// Renders the add-family form as a given role, for the browser-free checks.
// Usage: php tests/unit/render-form-as.php [role] -> storage/app/testing/family-form-<role>.html
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Family, Lookup, User};
use Illuminate\Support\Facades\Auth;

$role = $argv[1] ?? 'super_admin';
$user = User::where('role', $role)->when($role !== 'super_admin', fn ($q) => $q->whereNotNull('pradesh'))->firstOrFail();
Auth::setUser($user);
$html = view('admin.families.form', [
    'family' => new Family(['country' => 'INDIA', 'member_count' => 1, 'resident_type' => 'OWNED']),
    'lk' => Lookup::grouped(),
    'tehsilMeta' => Lookup::tehsilMetaFor($user),
    'casteGotras' => Lookup::casteGotras(),
    'cityByTehsil' => [],
    'errors' => new \Illuminate\Support\ViewErrorBag(),
])->render();
$dir = storage_path('app/testing');
if (! is_dir($dir)) mkdir($dir, 0775, true);
file_put_contents("$dir/family-form-$role.html", $html);
echo "$role ($user->pradesh): " . strlen($html) . " bytes\n";
