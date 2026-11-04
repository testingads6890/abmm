<?php

// Read-only cross-check of the location chain used by the member form:
// Country -> State -> District -> Tehsil, then ABMM Pradesh (from the state), then Anchal (from the Pradesh).
// Usage: php tests/unit/geo-mapping-check.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Lookup;

$fails = 0;
$meta = Lookup::tehsilMeta();
$anchalOf = [];
foreach (Lookup::anchalPradesh() as $a => $list) foreach ($list as $p) $anchalOf[$p] = $a;

// 1) Every tehsil has a full chain: country, state, district, and a Pradesh that has an Anchal.
$states = [];
foreach ($meta as $name => $m) {
    $states[$m['state']]['pradesh'][$m['pradesh'] ?? '(none)'] = true;
    $states[$m['state']]['districts'][$m['district']] = true;
    if (empty($m['country']) || empty($m['district']) || empty($m['state'])) {
        $fails++; echo "FAIL tehsil $name has a missing level\n";
    }
}

// 2) Each state maps to exactly one Pradesh, and that Pradesh has an Anchal.
$noAnchal = [];
$multi = [];
foreach ($states as $state => $info) {
    $pr = array_keys($info['pradesh']);
    if (count($pr) !== 1) { $multi[] = $state . ' -> ' . implode(' | ', $pr); $fails++; continue; }
    if ($pr[0] === '(none)') { $noAnchal[] = "$state (no Pradesh)"; $fails++; continue; }
    if (! isset($anchalOf[$pr[0]])) { $noAnchal[] = "$state -> {$pr[0]} (Pradesh not in any Anchal)"; $fails++; }
}
echo "states in tree: " . count($states) . "\n";
echo "states with one Pradesh each: " . (count($states) - count($multi)) . "\n";
foreach ($multi as $x) echo "FAIL more than one Pradesh: $x\n";
foreach ($noAnchal as $x) echo "FAIL $x\n";

// 3) Print the full mapping so it can be reviewed by hand.
echo "\nState -> ABMM Pradesh -> Anchal\n";
foreach ($states as $state => $info) {
    $p = array_keys($info['pradesh'])[0] ?? '-';
    printf("%-36s %-60s %s\n", $state, $p, $anchalOf[$p] ?? '-');
}

echo $fails ? "\n$fails issue(s) found\n" : "\nmapping is complete and consistent\n";
exit($fails ? 1 : 0);
