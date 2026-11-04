<?php

// Read-only data audit: counts and common data problems. Changes nothing.
// Usage: php tests/unit/data-audit.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$q = fn (string $sql, array $b = []) => DB::select($sql, $b);
$n = fn (string $sql) => (int) (DB::selectOne($sql)->c ?? 0);

echo "== Counts\n";
foreach (['families', 'members', 'lookups', 'meeting_list', 'users'] as $t) {
    echo str_pad($t, 16) . DB::table($t)->count() . "\n";
}
echo "families by status: " . json_encode(DB::table('families')->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c', 'status')) . "\n";
echo "members by status: " . json_encode(DB::table('members')->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c', 'status')) . "\n";

echo "\n== Problems\n";
$checks = [
    'members whose family does not exist' => "SELECT COUNT(*) c FROM members m LEFT JOIN families f ON f.id=m.family_id WHERE f.id IS NULL",
    'families with no head member' => "SELECT COUNT(*) c FROM families f WHERE NOT EXISTS (SELECT 1 FROM members m WHERE m.family_id=f.id AND m.is_head=1)",
    'families with more than one head' => "SELECT COUNT(*) c FROM (SELECT family_id FROM members WHERE is_head=1 GROUP BY family_id HAVING COUNT(*)>1) x",
    'families with no mobile' => "SELECT COUNT(*) c FROM families WHERE mobile IS NULL OR mobile=''",
    'families with a mobile that is not 10 digits' => "SELECT COUNT(*) c FROM families WHERE mobile REGEXP '[^0-9]' OR (LENGTH(mobile)<>10 AND mobile<>'')",
    'families with no tehsil' => "SELECT COUNT(*) c FROM families WHERE tehsil IS NULL OR tehsil=''",
    'families with no district' => "SELECT COUNT(*) c FROM families WHERE district IS NULL OR district=''",
    'families with no Pradesh' => "SELECT COUNT(*) c FROM families WHERE pradesh IS NULL OR pradesh=''",
    'families with no address' => "SELECT COUNT(*) c FROM families WHERE address IS NULL OR address=''",
    'families with a pincode that is not 6 digits' => "SELECT COUNT(*) c FROM families WHERE pincode IS NOT NULL AND pincode<>'' AND (LENGTH(pincode)<>6 OR pincode REGEXP '[^0-9]')",
    'duplicate family codes' => "SELECT COUNT(*) c FROM (SELECT family_code FROM families GROUP BY family_code HAVING COUNT(*)>1) x",
    'duplicate member codes' => "SELECT COUNT(*) c FROM (SELECT member_code FROM members WHERE member_code IS NOT NULL AND member_code<>'' GROUP BY member_code HAVING COUNT(*)>1) x",
    'families with state stored as a short code (2 letters)' => "SELECT COUNT(*) c FROM families WHERE CHAR_LENGTH(state)=2",
    'families whose state is not in Master Data' => "SELECT COUNT(*) c FROM families f WHERE f.state IS NOT NULL AND f.state<>'' AND NOT EXISTS (SELECT 1 FROM lookups l WHERE l.type='state' AND l.name=f.state)",
    'families whose tehsil is not in Master Data' => "SELECT COUNT(*) c FROM families f WHERE f.tehsil IS NOT NULL AND f.tehsil<>'' AND NOT EXISTS (SELECT 1 FROM lookups l WHERE l.type='tehsil' AND l.name=f.tehsil)",
    'families whose Pradesh has no Anchal' => "SELECT COUNT(*) c FROM families f WHERE f.pradesh IS NOT NULL AND f.pradesh<>'' AND NOT EXISTS (SELECT 1 FROM lookups p JOIN lookups a ON a.id=p.parent_id AND a.type='anchal' WHERE p.type='pradesh' AND p.name=f.pradesh)",
    'members with gender not Male/Female' => "SELECT COUNT(*) c FROM members WHERE gender IS NOT NULL AND gender NOT IN ('Male','Female')",
    'members with gender stored in capitals' => "SELECT COUNT(*) c FROM members WHERE BINARY gender IN ('MALE','FEMALE')",
    'members with no dob' => "SELECT COUNT(*) c FROM members WHERE dob IS NULL",
    'members with a dob in the future' => "SELECT COUNT(*) c FROM members WHERE dob > CURDATE()",
    'members older than 110' => "SELECT COUNT(*) c FROM members WHERE dob < DATE_SUB(CURDATE(), INTERVAL 110 YEAR)",
    'members whose family_id is null' => "SELECT COUNT(*) c FROM members WHERE family_id IS NULL",
    'families whose member_count differs from active members' => "SELECT COUNT(*) c FROM families f WHERE f.member_count <> (SELECT COUNT(*) FROM members m WHERE m.family_id=f.id AND m.status='active')",
    'duplicate lookup entries (same type and name and parent)' => "SELECT COUNT(*) c FROM (SELECT type,name,parent_id FROM lookups GROUP BY type,name,parent_id HAVING COUNT(*)>1) x",
];
foreach ($checks as $label => $sql) {
    try { $c = $n($sql); printf("%-58s %s\n", $label, $c === 0 ? 'ok' : "$c"); }
    catch (\Throwable $e) { printf("%-58s ERROR %s\n", $label, substr($e->getMessage(), 0, 80)); }
}

echo "\n== Sample of rows to review (first 5 each)\n";
foreach (['families with mobile not 10 digits' => "SELECT family_code, mobile FROM families WHERE mobile REGEXP '[^0-9]' OR (LENGTH(mobile)<>10 AND mobile<>'') LIMIT 5",
          'families with no Pradesh' => "SELECT family_code, tehsil, district, state FROM families WHERE pradesh IS NULL OR pradesh='' LIMIT 5",
          'families whose state is a short code' => "SELECT family_code, state, district FROM families WHERE CHAR_LENGTH(state)=2 LIMIT 5"] as $label => $sql) {
    echo "-- $label\n";
    foreach ($q($sql) as $r) echo '   ' . json_encode((array) $r) . "\n";
}
