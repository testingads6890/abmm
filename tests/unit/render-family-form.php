<?php

// Renders the add-family form to storage/app/testing/family-form.html so the browser-free JS test can run it.
// Usage: php tests/unit/render-family-form.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Family, Lookup, User};
use Illuminate\Support\Facades\Auth;

// The admin layout reads auth()->user(), so render as a Super Admin (no session is written from the CLI).
Auth::setUser(User::where('role', 'super_admin')->firstOrFail());

$html = view('admin.families.form', [
    'family' => new Family(['country' => 'INDIA', 'member_count' => 1, 'resident_type' => 'OWNED']),
    'lk' => Lookup::grouped(),
    'tehsilMeta' => Lookup::tehsilMeta(),
    'casteGotras' => Lookup::casteGotras(),
    'errors' => new \Illuminate\Support\ViewErrorBag(),
])->render();

$dir = storage_path('app/testing');
if (! is_dir($dir)) mkdir($dir, 0775, true);
file_put_contents("$dir/family-form.html", $html);
echo "written: $dir/family-form.html (" . strlen($html) . " bytes)\n";
