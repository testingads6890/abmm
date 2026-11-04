<?php

namespace App\Jobs;

use App\Models\{Member, NotificationLog};
use App\Services\FcmService;
use App\Services\Messaging\MessageService;
use App\Services\PhoneService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/** Notification with Send-To / blood group / vaccination filters, or sent over SMS / WhatsApp. Works per member (device token or mobile). */
class SendTargetedNotification implements ShouldQueue
{
    use Queueable;
    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(public int $logId) {}

    /** Active, approved members of the families recorded as recipients, narrowed by the filters. */
    public static function members(int $logId, array $f)
    {
        $q = Member::withoutGlobalScopes()->where('members.status', 'active')->where('members.approval', 'approved')
            ->whereIn('members.family_id', DB::table('notification_recipients')->where('notification_id', $logId)->select('family_id'));
        if (! empty($f['head_only'])) $q->where('members.is_head', true);
        if (! empty($f['gender'])) $q->where('members.gender', $f['gender']);
        if (! empty($f['blood_group'])) $q->where('members.blood_group', $f['blood_group']);
        if (! empty($f['vaccinated'])) $q->where('members.vaccinated', true);
        if (! empty($f['covid_recovered'])) $q->where('members.covid_recovered', true);
        return $q;
    }

    public function handle(FcmService $fcm, MessageService $msg): void
    {
        $log = NotificationLog::findOrFail($this->logId);
        $f = $log->filters ?: [];
        $sent = 0; $failed = 0; $last = null;
        foreach (self::members($log->id, $f)->orderBy('members.id')->cursor() as $m) {
            try {
                if ($log->channel === 'app') {
                    $tokens = DB::table('api_tokens as t')->join('users as u', 'u.id', '=', 't.user_id')->where('u.member_id', $m->id)->whereNotNull('t.fcm_token')
                        ->where(fn ($q) => $q->whereNull('t.expires_at')->orWhere('t.expires_at', '>', now()))->distinct()->pluck('t.fcm_token');
                    foreach ($tokens as $t) { $fcm->sendToToken($t, $log->title, $log->message, ['notification_id' => $log->id, 'event_id' => $log->event_id]); $sent++; }
                } elseif ($m->mobile) {
                    $to = PhoneService::e164($m->mobile, $m->country_code ?: '+91');
                    $ok = $log->channel === 'sms' ? $msg->sms($to, $log->title . ': ' . $log->message, 'utility', $m->family_id)
                        : $msg->whatsapp($to, (string) config('abmm.whatsapp.notice_template', 'notice'), [$m->name, $log->message], 'utility', null, null, $m->family_id);
                    $ok ? $sent++ : $failed++;
                }
            } catch (\Throwable $e) { $failed++; $last = $e->getMessage(); }
        }
        $log->update(['delivery_status' => $sent > 0 || $failed === 0 ? 'sent' : 'failed', 'error' => $failed ? "$failed failed" . ($last ? ": $last" : '') : null]);
    }
}
