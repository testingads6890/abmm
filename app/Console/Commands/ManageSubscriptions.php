<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/** php artisan abmm:subscriptions — expires lapsed plans and reminds those ending within 7 days. Daily 06:00. */
class ManageSubscriptions extends Command
{
    protected $signature = 'abmm:subscriptions {--days=7}';
    protected $description = 'Expire lapsed subscriptions and send renewal reminders';

    public function handle(SubscriptionService $s): int
    {
        $this->info('Expired: ' . $s->expireDue() . ' | Reminded: ' . $s->remindDue((int) $this->option('days')));
        return self::SUCCESS;
    }
}
