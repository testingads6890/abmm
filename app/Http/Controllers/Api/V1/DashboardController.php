<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Family, Member};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user();
        $key = 'dash:' . $u->id . ':' . md5(json_encode($u->areaFilter()));
        return Cache::remember($key, 600, fn () => [   // 10 min cache: counting 10 lakh rows on every app open is wasteful
            'families' => Family::where('is_active', true)->count(),
            'members' => Member::where('status', 'active')->count(),
            'latest_families' => Family::latest('id')->limit(8)->get(['id', 'family_code', 'surname', 'head_name', 'district', 'mobile', 'member_count']),
        ]);
    }
}
