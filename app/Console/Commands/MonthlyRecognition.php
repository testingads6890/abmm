<?php

namespace App\Console\Commands;

use App\Services\{CertificateService, PointsService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** php artisan abmm:monthly-recognition [--month=YYYY-MM] [--top=3] — "Star of the Month" per country: certificate + badge + notification. Runs on the 1st at 08:00. */
class MonthlyRecognition extends Command
{
    protected $signature = 'abmm:monthly-recognition {--month=} {--top=3}';
    protected $description = 'Recognise the top point earners of the previous month in every country';

    public function handle(PointsService $points, CertificateService $certs): int
    {
        $month = $this->option('month') ?: now()->subMonthNoOverflow()->format('Y-m');
        $badge = DB::table('badges')->where('code', 'star_of_month')->first();
        $n = 0;
        $countries = DB::table('point_ledger')->whereNotNull('country')->distinct()->pluck('country');
        foreach ($countries as $country) {
            $rows = $points->leaderboard('month', ['country' => $country], (int) $this->option('top'), $month);
            foreach ($rows as $i => $row) {
                if ($row->points <= 0) continue;
                $rank = $i + 1;
                $ok = $certs->issue($row->user_id, DB::table('users')->where('id', $row->user_id)->value('family_id'), 'star_of_month',
                    "Star of the Month #{$rank} - " . \Carbon\Carbon::parse("$month-01")->format('F Y'), "{$row->points} points earned in $country", $month);
                if ($ok && $badge) DB::table('user_badges')->insertOrIgnore(['user_id' => $row->user_id, 'badge_id' => $badge->id, 'ref' => $month, 'created_at' => now()]);
                if ($ok) $n++;
            }
        }
        $this->info("Recognised $n members for $month");
        return self::SUCCESS;
    }
}
