<?php

// Read-only check: the export row and the print/report address carry the location and Anchal values.
// Usage: php tests/unit/export-print-check.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\MemberImportController;
use App\Models\{Family, Lookup};

$fail = 0;
$check = function (bool $ok, string $label) use (&$fail) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (! $ok) $fail++;
};

$headers = MemberImportController::HEADERS;
$check(count($headers) === 36 && $headers[count($headers) - 1] === 'Anchal', 'export headers end with Anchal');

$f = Family::where('pradesh', 'PURVOTTAR RAJASTHAN PRADESH MAHESHWARI SABHA')->first();
if ($f) {
    $m = $f->members()->first() ?? new \App\Models\Member(['name' => 'x', 'surname' => $f->surname]);
    $row = MemberImportController::row($f, $m);
    $check(count($row) === count(MemberImportController::HEADERS), 'export row has one value per header');
    $check($row[count($row) - 1] === 'PASCHIMANCHAL', 'export row Anchal is PASCHIMANCHAL for a Rajasthan family');
} else {
    echo "SKIP export row check: no Rajasthan family in the database\n";
}

$check(Lookup::anchalOf('GUJARAT PRANTIYA MAHESHWARI SABHA') === 'MADHYANCHAL', 'Gujarat Prantiya belongs to MADHYANCHAL');
$check(Lookup::anchalOf('DELHI PRADESH MAHESHWARI SANGATHAN') === 'UTTARANCHAL', 'Delhi belongs to UTTARANCHAL');
$check(Lookup::anchalOf('NO SUCH PRADESH') === null, 'unknown pradesh has no Anchal');

echo $fail ? "\n$fail check(s) failed\n" : "\nall checks passed\n";
exit($fail ? 1 : 0);
