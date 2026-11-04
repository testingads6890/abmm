<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotification;
use App\Models\{Event, Family, NotificationLog};
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Exporter;

class NotificationController extends Controller
{
    private const FIELD = ['pradesh' => 'pradesh', 'state' => 'state', 'district' => 'district', 'tehsil' => 'tehsil', 'city' => 'city', 'zone' => 'zone'];

    public function index(Request $r, FcmService $fcm)
    {
        $rows = NotificationLog::with('sender')
            ->when($r->tehsil, fn ($q, $v) => $q->where('audience', 'tehsil')->where('audience_value', $v))
            ->when($r->from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($r->to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest();
        if (in_array($r->export, ['csv', 'xlsx', 'pdf'], true)) {
            $gen = (function () use ($rows) { foreach ((clone $rows)->reorder()->lazyById(500) as $n) yield [$n->sender?->name, ucfirst($n->audience) . ' ' . $n->audience_value, $n->title . ' — ' . $n->message, $n->member_count, $n->seen_count, max(0, $n->member_count - $n->seen_count), $n->created_at?->format('d-M-Y')]; })();
            return Exporter::download($r->export, 'abmm-notifications', 'Notification', ['Sent By', 'Sent To', 'Message', 'Member Count', 'Seen', 'Not Seen', 'Date'], $gen);
        }
        $rows = $rows->paginate(in_array((int) $r->per, [10, 25, 50, 100]) ? (int) $r->per : 100)->withQueryString();
        return view('admin.notifications.index', ['rows' => $rows, 'fcmReady' => $fcm->configured(), 'tehsils' => \App\Models\Lookup::ofType('tehsil')->active()->orderBy('name')->pluck('name'),
            'print' => $r->boolean('print')]);
    }

    public function store(Request $r, FcmService $fcm)
    {
        $d = $r->validate([
            'audience' => 'required|in:country,anchal,pradesh,state,district,tehsil,city,zone', 'audience_value' => 'required_unless:audience,country|nullable|max:120',
            'kind' => 'required|in:notice,meeting', 'title' => 'required|max:120', 'message' => 'required|max:200',
            'event_date' => 'required_if:kind,meeting|nullable|date|after_or_equal:today', 'event_time' => 'nullable', 'venue' => 'required_if:kind,meeting|nullable|max:200',
            'channel' => 'nullable|in:app,sms,whatsapp', 'send_to' => 'nullable|in:all,Male,Female,head', 'blood_group' => 'nullable|string|max:5',
        ]);
        $channel = $d['channel'] ?? 'app';
        $filters = array_filter(['gender' => in_array($d['send_to'] ?? null, ['Male', 'Female'], true) ? $d['send_to'] : null, 'head_only' => ($d['send_to'] ?? null) === 'head' ? true : null,
            'blood_group' => $d['blood_group'] ?? null, 'vaccinated' => $r->boolean('vaccinated') ?: null, 'covid_recovered' => $r->boolean('covid_recovered') ?: null]);
        $targeted = $channel !== 'app' || $filters || in_array($d['audience'], ['city', 'zone', 'anchal'], true);     // plain app push to a whole area uses the cheap FCM topic; Anchal has no topic, so it goes per member
        if (! $targeted && ! $fcm->configured()) {
            return back()->withInput()->withErrors(['message' => 'Firebase is not configured (.env: FCM_PROJECT_ID and the service-account JSON).']);
        }
        if ($channel === 'app' && $targeted && ! $fcm->configured()) return back()->withInput()->withErrors(['message' => 'Firebase is not configured.']);
        $fam = Family::where('status', 'active');
        if ($d['audience'] === 'anchal') {   // every ABMM Pradesh under the Anchal
            $list = \App\Models\Lookup::pradeshesOf($d['audience_value']);
            if (! $list) return back()->withInput()->withErrors(['audience_value' => 'Unknown Anchal, or no ABMM Pradesh is linked to it (Master Data > ABMM Pradesh).']);
            $fam->whereIn('pradesh', $list);
        } elseif ($d['audience'] !== 'country') $fam->where(self::FIELD[$d['audience']], $d['audience_value']);

        $log = DB::transaction(function () use ($d, $r, $fam, $channel, $filters) {
            $event = $d['kind'] === 'meeting' ? Event::create([
                'title' => $d['title'], 'description' => $d['message'], 'venue' => $d['venue'], 'event_date' => $d['event_date'], 'event_time' => $d['event_time'] ?? null,
                'kind' => 'meeting', 'audience' => $d['audience'], 'audience_value' => $d['audience_value'] ?? null, 'is_public' => false, 'created_by' => $r->user()->id,
            ]) : null;
            $log = NotificationLog::create([
                'sent_by' => $r->user()->id, 'audience' => $d['audience'], 'audience_value' => $d['audience_value'] ?? null, 'channel' => $channel, 'filters' => $filters ?: null, 'kind' => $d['kind'],
                'title' => $d['title'], 'message' => $d['message'], 'event_id' => $event?->id, 'member_count' => (int) (clone $fam)->count(),
                'topic' => FcmService::topicFor($d['audience'], $d['audience_value'] ?? null), 'delivery_status' => 'queued',
            ]);
            // one row per family so Seen / Not Seen can be tracked
            (clone $fam)->select('id')->chunkById(1000, fn ($chunk) => DB::table('notification_recipients')->insertOrIgnore(
                $chunk->map(fn ($f) => ['notification_id' => $log->id, 'family_id' => $f->id])->all()));
            return $log;
        });
        if ($targeted) {
            \App\Jobs\SendTargetedNotification::dispatch($log->id);
            return back()->with('ok', 'Notification queued (' . strtoupper($channel) . ($filters ? ', filters lagaye' : '') . '). Queue worker chalta rahe.');
        }
        SendPushNotification::dispatch($log->id);
        return back()->with('ok', "Push notification queued (topic: {$log->topic}). Keep the queue worker running: php artisan queue:work");
    }

    public function show(NotificationLog $notification)
    {
        $seen = DB::table('notification_recipients')->where('notification_id', $notification->id);
        return view('admin.notifications.show', [
            'n' => $notification->load('sender'),
            'seen' => (clone $seen)->whereNotNull('seen_at')->count(), 'total' => (clone $seen)->count(),
            'recipients' => Family::select('families.*', 'nr.seen_at')->join('notification_recipients as nr', 'nr.family_id', '=', 'families.id')
                ->where('nr.notification_id', $notification->id)->orderByRaw('nr.seen_at is null')->paginate(50),
        ]);
    }

    public function resend(NotificationLog $notification)
    {
        $notification->update(['delivery_status' => 'queued', 'error' => null]);
        if ($notification->channel !== 'app' || $notification->filters) \App\Jobs\SendTargetedNotification::dispatch($notification->id);
        else SendPushNotification::dispatch($notification->id);
        return back()->with('ok', 'Queued again.');
    }

    public function destroy(NotificationLog $notification)
    {
        $notification->delete();
        return back()->with('ok', 'Notification record removed.');
    }

    /** "My Meetings / Events": meeting notifications. */
    public function meetings(Request $r)
    {
        $rows = NotificationLog::with(['sender', 'event'])->where('kind', 'meeting')
            ->when($r->from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))->when($r->to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()->paginate(in_array((int) $r->per, [10, 25, 50, 100]) ? (int) $r->per : 25)->withQueryString();
        return view('admin.notifications.meetings', compact('rows'));
    }
}
