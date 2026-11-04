<?php

namespace App\Console\Commands;

use App\Services\{AcademyService, ExamService};
use Illuminate\Console\Command;

/** php artisan abmm:learning-run : weekly exams (create, close, ranks, certificates, monthly awards) and live-class reminders. Scheduled hourly. */
class LearningRun extends Command
{
    protected $signature = 'abmm:learning-run';
    protected $description = 'Weekly exam lifecycle, monthly awards and live class reminders';

    public function handle(ExamService $exams, AcademyService $academy): int
    {
        $made = $exams->ensureWeekly();
        $closed = $exams->closeDue();
        $awards = now()->day <= 3 ? $exams->monthlyAwards() : 0;
        $rem = $academy->remindClasses();
        $this->info("Exams created: $made, closed: $closed, monthly awards: $awards, class reminders: $rem");
        return self::SUCCESS;
    }
}
