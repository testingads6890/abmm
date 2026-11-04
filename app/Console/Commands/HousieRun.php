<?php

namespace App\Console\Commands;

use App\Services\HousieService;
use Illuminate\Console\Command;

/** php artisan abmm:housie-run : starts due Housie games, calls numbers, sends 30-minute reminders. Scheduled every minute. */
class HousieRun extends Command
{
    protected $signature = 'abmm:housie-run';
    protected $description = 'Housie game runner';

    public function handle(HousieService $h): int
    {
        $r = $h->run();
        $this->info('Started ' . $r['started'] . ', numbers called ' . $r['called'] . ', reminders ' . $r['reminded']);
        return self::SUCCESS;
    }
}
