<?php

// Renders a few admin pages as a Super Admin into storage/app/testing/layout/ so a headless browser can screenshot them.
// The CSS and JS still load from the live site, as in production. Nothing is saved to the database.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\{FamilyController, MeetingListController};
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$dir = storage_path('app/testing/layout');
if (! is_dir($dir)) mkdir($dir, 0775, true);
$user = User::where('role', 'super_admin')->firstOrFail();
Auth::setUser($user);
view()->share('errors', new \Illuminate\Support\ViewErrorBag());

$req = Request::create('/admin/families', 'GET');
$req->setUserResolver(fn () => $user);
$families = (new FamilyController())->index($req);
file_put_contents("$dir/families.html", $families->render());

$req2 = Request::create('/admin/meeting-list', 'GET');
$req2->setUserResolver(fn () => $user);
file_put_contents("$dir/meetings.html", (new MeetingListController())->index($req2)->render());

echo "rendered: families.html, meetings.html in $dir\n";
