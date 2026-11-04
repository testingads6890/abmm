<?php

namespace App\Console\Commands;

use App\Services\{BloodService, CareerService};
use Illuminate\Console\Command;

/** php artisan abmm:career-jobs : daily job-alert digests + mentoring session reminders (scheduled hourly). */
class CareerJobs extends Command
{
    protected $signature = 'abmm:career-jobs';
    protected $description = 'Job alert digests and mentor session reminders';

    public function handle(CareerService $c): int
    {
        $this->info('Job digests: ' . $c->digest() . ', session reminders: ' . $c->remindSessions());
        return self::SUCCESS;
    }
}
