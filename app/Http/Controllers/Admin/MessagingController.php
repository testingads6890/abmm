<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBroadcast;
use App\Models\{ActivityLog, Zone};
use App\Services\AudienceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** WhatsApp / SMS / e-mail broadcasts with nation > country > state > district > city > zone targeting, opt-in enforcement and cost estimate. */
class MessagingController extends Controller
{
    public function __construct(private AudienceService $aud) {}

    /** A non-super sender can only target inside own area: the area columns are forced from the user. */
    private function target(Request $r): array
    {
        $t = $r->only(['level', 'country', 'state', 'district', 'city', 'zone']);
        $t['level'] = $t['level'] ?? 'all';
        $u = $r->user();
        if (! $u->isSuper()) {
            abort_unless($u->hasArea(), 403);
            foreach ($u->areaFilter() as $col => $val) $t[$col] = $val;
            $t['level'] = $t['level'] === 'all' ? ($u->isGlobalRole() ? 'country' : 'state') : $t['level'];
        }
        return $t;
    }

    public function index(Request $r)
    {
        $since = now()->subDays(30);
        return view('admin.global.messaging', [
            'levels' => AudienceService::LEVELS, 'zones' => Zone::active()->orderBy('city')->orderBy('sort')->get(['country', 'city', 'name']),
            'stats' => DB::table('message_logs')->where('created_at', '>=', $since)->selectRaw('channel, status, COUNT(*) c, SUM(cost) cost')->groupBy('channel', 'status')->get(),
            'recent' => DB::table('message_logs')->orderByDesc('id')->limit(30)->get(),
            'optins' => DB::table('message_optins')->where('channel', 'whatsapp')->selectRaw('opted_in, COUNT(*) c')->groupBy('opted_in')->pluck('c', 'opted_in'),
            'wa' => app(\App\Services\Messaging\MessageService::class)->whatsappConfigured(),
        ]);
    }

    public function estimate(Request $r)
    {
        $t = $this->target($r);
        $n = $this->aud->count($t);
        $cat = $r->input('category', 'marketing');
        $rate = (float) config("abmm.whatsapp.cost.$cat", 0) * 1.18;
        return response()->json(['families' => $n, 'whatsapp_cost_inr_incl_gst' => round($n * $rate, 2), 'rate_per_message' => round($rate, 4)]);
    }

    public function send(Request $r)
    {
        $d = $r->validate(['channel' => 'required|in:whatsapp,sms,email', 'category' => 'required|in:utility,marketing', 'text' => 'required|string|max:900', 'template' => 'nullable|string|max:80', 'subject' => 'nullable|string|max:150',
            'level' => 'nullable|in:all,country,state,district,city,zone']);
        abort_if($d['channel'] === 'whatsapp' && empty($d['template']), 422, 'An approved template name is required for WhatsApp.');
        $t = $this->target($r);
        $n = $this->aud->count($t);
        abort_if($n === 0, 422, 'No family found in this target.');
        SendBroadcast::dispatch($t, $d['channel'], $d['category'], $d['text'], $d['template'] ?? null, $d['subject'] ?? null);
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'BROADCAST', 'detail' => substr("{$d['channel']} {$t['level']} n=$n", 0, 250), 'ip' => $r->ip()]);
        return back()->with('ok', "$n families were queued for {$d['channel']} messages (marketing is skipped without opt-in). The queue worker must be running.");
    }
}
