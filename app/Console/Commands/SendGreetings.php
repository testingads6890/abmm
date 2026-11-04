<?php

namespace App\Console\Commands;

use App\Services\GreetingService;
use Illuminate\Console\Command;

/** php artisan abmm:greetings  — runs daily at 07:00 (scheduled in AppServiceProvider). Needs `php artisan schedule:run` in cron every minute. */
class SendGreetings extends Command
{
    protected $signature = 'abmm:greetings';
    protected $description = 'Send birthday and anniversary greetings to today\'s celebrating app members';

    public function handle(GreetingService $svc): int
    {
        $r = $svc->sendDaily();
        $this->info("Birthday: {$r['birthday']}, Anniversary: {$r['anniversary']}");
        return self::SUCCESS;
    }
}
