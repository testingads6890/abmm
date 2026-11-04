<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Staff app: the admin reports as JSON, with the same filters (GET /api/v1/reports/{type}?q=&tehsil=&page=). */
class ReportApiController extends Controller
{
    public function index(Request $r, string $type)
    {
        return app(ReportController::class)->apiRows($type, $r);
    }
}
