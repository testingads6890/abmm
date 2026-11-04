<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentLedger;
use Illuminate\Console\Command;

/** php artisan abmm:reconcile-payments — asks Razorpay/Stripe about payments still "created"; settles those that were actually paid. */
class ReconcilePayments extends Command
{
    protected $signature = 'abmm:reconcile-payments';
    protected $description = 'Settle payments whose webhook/callback never arrived';

    public function handle(PaymentLedger $l): int
    {
        $r = $l->reconcile();
        $this->info("Checked {$r['checked']}, settled {$r['settled']}, failed {$r['failed']}");
        return self::SUCCESS;
    }
}
