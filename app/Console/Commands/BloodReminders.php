<?php

namespace App\Console\Commands;

use App\Services\BloodService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** php artisan abmm:blood-reminders : tells donors who became eligible again, expires old open requests (scheduled daily 10:00). */
class BloodReminders extends Command
{
    protected $signature = 'abmm:blood-reminders';
    protected $description = 'Blood donor re-eligibility reminders and request expiry';

    public function handle(BloodService $b): int
    {
        $n = $b->remindEligible();
        $x = DB::table('blood_requests')->where('status', 'open')->where(fn ($q) => $q->where(fn ($w) => $w->whereNotNull('needed_by')->where('needed_by', '<', today()->subDay()->toDateString()))
            ->orWhere('created_at', '<', now()->subDays(10)))->update(['status' => 'closed', 'updated_at' => now()]);
        $this->info("Reminders: $n, requests closed: $x");
        return self::SUCCESS;
    }
}
