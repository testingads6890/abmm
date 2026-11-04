<?php

// Saves a meeting with an attached file as Super Admin and as a State Sub-Admin, then rolls everything back.
// The file goes to a faked disk, so nothing is written to storage. Nothing is kept in the database.
// Usage: php tests/unit/meeting-save-check.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\MeetingListController;
use App\Models\{MeetingEntry, User};
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, DB, Storage};

Storage::fake('public');
$fails = 0;
$check = function (bool $ok, string $label) use (&$fails) { echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n"; if (! $ok) $fails++; };

$request = function (User $u, array $fields, $file = null) {
    $r = Request::create('/admin/meeting-list', 'POST', $fields, [], $file ? ['attachment' => $file] : []);
    $r->setUserResolver(fn () => $u);
    Auth::setUser($u);
    return $r;
};
$base = [
    'committee' => array_key_first(MeetingEntry::COMMITTEES),
    'meeting_type' => array_key_first(MeetingEntry::TYPES),
    'venue' => 'Test Bhawan', 'location' => 'Test road, Test city',
    'meeting_date' => now()->addDay()->toDateString(), 'meeting_time' => '18:30', 'agenda' => 'Check agenda',
];
$ctl = new MeetingListController();

// 1) Super Admin saves a meeting with a PDF, inside a transaction that is rolled back.
$super = User::where('role', 'super_admin')->firstOrFail();
$pradesh = 'PURVOTTAR RAJASTHAN PRADESH MAHESHWARI SABHA';
DB::beginTransaction();
try {
    $before = MeetingEntry::count();
    $pdf = UploadedFile::fake()->create('minutes.pdf', 20, 'application/pdf');
    $resp = $ctl->store($request($super, $base + ['pradesh' => $pradesh], $pdf));
    $saved = MeetingEntry::latest('id')->first();
    $check($resp->getStatusCode() === 302, 'super admin: save redirects back to the list');
    $check(MeetingEntry::count() === $before + 1, 'super admin: one meeting row created');
    $check($saved && $saved->district === 'JAIPUR' && $saved->anchal === 'PASCHIMANCHAL', 'super admin: district and Anchal looked up from the Pradesh');
    $check($saved && (int) $saved->created_by === (int) $super->id, 'super admin: created_by is the logged-in user');
    $check($saved && $saved->attachment && Storage::disk('public')->exists($saved->attachment), 'super admin: attachment stored on the public disk');
    $check($saved && str_ends_with((string) $saved->attachment, '.pdf'), 'super admin: attachment keeps the .pdf extension');
} catch (\Throwable $e) {
    $fails++; echo "FAIL super admin save: " . substr($e->getMessage(), 0, 200) . "\n";
} finally {
    DB::rollBack();
}

// 2) State Sub-Admin saves in their own Pradesh, without a file.
$sub = User::where('role', 'state_admin')->whereNotNull('pradesh')->get()->first(fn ($x) => $x->pradesh);
if ($sub) {
    DB::beginTransaction();
    try {
        $resp = $ctl->store($request($sub, $base + ['pradesh' => $sub->pradesh]));
        $saved = MeetingEntry::latest('id')->first();
        $check($resp->getStatusCode() === 302 && $saved && $saved->pradesh === $sub->pradesh, 'sub-admin: saves in own Pradesh');
        $check($saved && $saved->attachment === null, 'sub-admin: saved with no attachment');
    } catch (\Throwable $e) {
        $fails++; echo "FAIL sub-admin save: " . substr($e->getMessage(), 0, 200) . "\n";
    } finally {
        DB::rollBack();
    }
}

// 3) Rejections: a script file is not accepted; a Pradesh outside the sub-admin's area is refused.
foreach (['virus.exe' => 'application/octet-stream', 'notes.txt' => 'text/plain'] as $name => $mime) {
    try {
        $ctl->store($request($super, $base + ['pradesh' => $pradesh], UploadedFile::fake()->create($name, 10, $mime)));
        $check(false, "file $name is refused");
    } catch (\Illuminate\Validation\ValidationException $e) {
        $check(true, "file $name is refused");
    }
}
if ($sub) {
    try {
        DB::beginTransaction();
        $ctl->store($request($sub, $base + ['pradesh' => 'GUJARAT PRANTIYA MAHESHWARI SABHA']));
        $check(false, 'sub-admin cannot save for another Pradesh');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        $check($e->getStatusCode() === 422, 'sub-admin cannot save for another Pradesh (422)');
    } finally {
        DB::rollBack();
    }
}

echo $fails ? "\n$fails check(s) failed\n" : "\nall meeting checks passed (nothing kept in the database)\n";
exit($fails ? 1 : 0);
