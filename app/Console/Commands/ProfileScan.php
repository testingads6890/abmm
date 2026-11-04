<?php

namespace App\Console\Commands;

use App\Models\MatrimonyProfile;
use App\Services\FraudService;
use Illuminate\Console\Command;

/** php artisan abmm:profile-scan : re-checks every live Parinay profile for duplicates / shared mobiles / blocklist hits and files flags for the admin queue. Nightly 02:00. */
class ProfileScan extends Command
{
    protected $signature = 'abmm:profile-scan';
    protected $description = 'Duplicate and fraud scan of Parinay profiles';

    public function handle(FraudService $fraud): int
    {
        $n = 0;
        MatrimonyProfile::withoutGlobalScopes()->where('status', '!=', 'removed')->chunkById(200, function ($rows) use ($fraud, &$n) { foreach ($rows as $p) $n += count($fraud->scan($p)); });
        $this->info("new flags: $n");
        return self::SUCCESS;
    }
}
