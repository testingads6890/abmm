<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LaunchCheck;
use Illuminate\Http\Request;

/** Super admin only: launch readiness report (same checks as `php artisan abmm:go-live-check`). */
class LaunchController extends Controller
{
    public function index(Request $r)
    {
        abort_unless($r->user()->isSuper(), 403);
        $c = (new LaunchCheck())->run();
        return view('admin.system.launch', ['checks' => $c, 'sum' => LaunchCheck::summary($c)]);
    }
}
