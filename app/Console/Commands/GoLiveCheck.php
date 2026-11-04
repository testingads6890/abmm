<?php

namespace App\Console\Commands;

use App\Services\LaunchCheck;
use Illuminate\Console\Command;

/** php artisan abmm:go-live-check [--json] : pre-launch readiness report. Exit code 1 if any check FAILS. */
class GoLiveCheck extends Command
{
    protected $signature = 'abmm:go-live-check {--json}';
    protected $description = 'Production readiness report (OTP, payments, queue, cron, backups, demo data...)';

    public function handle(): int
    {
        $c = (new LaunchCheck())->run(); $s = LaunchCheck::summary($c);
        if ($this->option('json')) { $this->line(json_encode(['summary' => $s, 'checks' => $c], JSON_PRETTY_PRINT)); return $s['ready'] ? self::SUCCESS : self::FAILURE; }
        $this->table(['Check', 'Status', 'Detail'], array_map(fn ($x) => [$x['name'], strtoupper($x['status']), $x['detail']], $c));
        foreach ($c as $x) if ($x['status'] !== 'ok' && $x['fix']) $this->line(strtoupper($x['status']) . ' - ' . $x['name'] . ': ' . $x['fix']);
        $this->info("OK {$s['ok']}  WARN {$s['warn']}  FAIL {$s['fail']}  => " . ($s['ready'] ? 'READY to go live (review warnings)' : 'NOT ready'));
        return $s['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
