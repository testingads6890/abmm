<?php

namespace App\Console\Commands;

use App\Services\AccountDeletionService;
use Illuminate\Console\Command;

/** php artisan abmm:erase-accounts : erases accounts whose 30-day deletion grace period is over (scheduled daily 04:10). */
class EraseAccounts extends Command
{
    protected $signature = 'abmm:erase-accounts';
    protected $description = 'Erase personal data of accounts past their deletion grace period';

    public function handle(AccountDeletionService $s): int { $this->info('Erased: ' . $s->eraseDue()); return self::SUCCESS; }
}
