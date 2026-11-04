<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Support\MemberApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Member app home: today's Daily message + festival + next festivals. Cached per day and pradesh, so 10 lakh opens hit the DB once per pradesh. */
class DailyController extends Controller
{
    public function today(Request $r)
    {
        $pradesh = Family::withoutGlobalScopes()->whereKey($r->user()->family_id)->value('pradesh');
        $key = 'api.daily.' . Cache::get('api.daily.v', '0') . '.' . today()->toDateString() . '.' . md5((string) $pradesh);

        return Cache::remember($key, 600, function () use ($pradesh) {
            $day = today()->toDateString();
            $area = fn ($q) => $q->where(fn ($w) => $w->whereNull('pradesh')->when($pradesh, fn ($x) => $x->orWhere('pradesh', $pradesh)));

            $msg = $area(DB::table('daily_messages'))->where('show_on', $day)->where('is_active', true)->orderByRaw('pradesh is null')->first();
            $fest = $area(DB::table('festivals'))->where('festival_date', $day)->where('is_active', true)->orderByRaw('pradesh is null')->first();
            $next = $area(DB::table('festivals'))->where('is_active', true)->whereBetween('festival_date', [today()->addDay()->toDateString(), today()->addDays(30)->toDateString()])
                ->orderBy('festival_date')->limit(3)->get(['name', 'festival_date']);

            return [
                'date' => $day,
                'message' => $msg ? ['tithi' => $msg->tithi, 'body_hi' => $msg->body_hi, 'body_en' => $msg->body_en, 'image_url' => MemberApp::photoUrl($msg->image)] : null,
                'festival' => $fest ? ['name' => $fest->name, 'message' => $fest->message, 'banner_url' => MemberApp::photoUrl($fest->banner)] : null,
                'upcoming' => $next->map(fn ($f) => ['name' => $f->name, 'date' => $f->festival_date])->all(),
            ];
        });
    }
}
