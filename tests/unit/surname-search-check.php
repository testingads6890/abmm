<?php

// Read-only check: a typed surname matches part of the name, and the dropdown still works.
// Usage: php tests/unit/surname-search-check.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\FamilyController;
use Illuminate\Http\Request;

$ctl = new FamilyController();
$count = function (array $input) use ($ctl) {
    $view = $ctl->index(Request::create('/admin/families', 'GET', $input));
    return $view->getData()['families']->total();
};

$all = $count(['status' => 'active']);
$surname = App\Models\Family::where('status', 'active')->value('surname');
$part = mb_substr((string) $surname, 0, 2);
$exact = $count(['surname' => [$surname]]);
$typed = $count(['surname_text' => $part]);
$typedFull = $count(['surname_text' => $surname]);

echo "all active=$all\n";
echo "dropdown exact '$surname' = $exact\n";
echo "typed part '$part' = $typed\n";
echo "typed full '$surname' = $typedFull\n";
$ok = $exact >= 1 && $typed >= $exact && $typedFull === $exact;
echo $ok ? "PASS\n" : "FAIL\n";
exit($ok ? 0 : 1);
