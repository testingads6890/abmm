<?php

namespace App\Console\Commands;

use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** php artisan abmm:festivals — pushes today's festival greetings (topic all / pradesh_<slug>); runs daily 07:30. */
class SendFestivals extends Command
{
    protected $signature = 'abmm:festivals';
    protected $description = 'Push today\'s festival greetings to the app';

    public function handle(FcmService $fcm): int
    {
        if (! $fcm->configured()) { $this->warn('FCM is not configured, skipped.'); return self::SUCCESS; }
        $sent = 0;
        DB::table('festivals')->where('festival_date', today()->toDateString())->where('is_active', true)->where('notify', true)->whereNull('notified_at')->get()
            ->each(function ($f) use ($fcm, &$sent) {
                try {
                    $topic = FcmService::topicFor($f->pradesh ? 'pradesh' : 'country', $f->pradesh);
                    $fcm->sendToTopic($topic, $f->name, $f->message, ['type' => 'festival']);
                    DB::table('festivals')->where('id', $f->id)->update(['notified_at' => now()]);
                    $sent++;
                } catch (\Throwable $e) { report($e); $this->error($f->name . ': ' . $e->getMessage()); }
            });
        $this->info("Festival push: $sent");
        return self::SUCCESS;
    }
}
