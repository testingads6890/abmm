<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $logId) {}

    public function handle(FcmService $fcm): void
    {
        $log = NotificationLog::findOrFail($this->logId);
        try {
            $id = $fcm->sendToTopic($log->topic, $log->title, $log->message, ['notification_id' => $log->id, 'event_id' => $log->event_id]);
            $log->update(['delivery_status' => 'sent', 'fcm_message_id' => $id, 'error' => null]);
        } catch (\Throwable $e) {
            $log->update(['delivery_status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
