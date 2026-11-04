<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * php artisan abmm:backup [--files] : gzip'd database dump in storage/app/backups (MySQL via mysqldump, SQLite file copy), optional uploads archive.
 * Old backups are deleted after BACKUP_KEEP_DAYS (default 14). Scheduled daily 03:30. Copy the folder off-server (rclone / S3 / Drive) as well.
 */
class Backup extends Command
{
    protected $signature = 'abmm:backup {--files : also archive storage/app/private and storage/app/public}';
    protected $description = 'Database (and optional uploads) backup with retention';

    public function handle(): int
    {
        $dir = storage_path('app/backups'); if (! is_dir($dir)) mkdir($dir, 0750, true);
        $stamp = now()->format('Ymd-His') . '-' . substr(uniqid(), -4); $db = config('database.default'); $c = config("database.connections.$db");
        $out = "$dir/db-$stamp.sql.gz";
        if (($c['driver'] ?? '') === 'mysql' || ($c['driver'] ?? '') === 'mariadb') {
            $cnf = tempnam(sys_get_temp_dir(), 'my'); chmod($cnf, 0600);
            file_put_contents($cnf, "[client]\nuser=\"{$c['username']}\"\npassword=\"{$c['password']}\"\nhost=\"{$c['host']}\"\nport=\"" . ($c['port'] ?? 3306) . "\"\n");
            $p = Process::fromShellCommandline('mysqldump --defaults-extra-file="$CNF" --single-transaction --quick --routines "$DB" | gzip > "$OUT"', null, ['CNF' => $cnf, 'DB' => $c['database'], 'OUT' => $out]);
            $p->setTimeout(1800); $p->run(); @unlink($cnf);
            if (! $p->isSuccessful() || ! is_file($out) || filesize($out) < 100) { @unlink($out); $this->error('mysqldump failed: ' . trim($p->getErrorOutput())); return self::FAILURE; }
        } elseif (($c['driver'] ?? '') === 'sqlite' && is_file($c['database'])) {
            file_put_contents($out, gzencode(file_get_contents($c['database'])));
        } else { $this->error('Unsupported database driver for backup.'); return self::FAILURE; }
        chmod($out, 0640); $this->info('Database backup: ' . basename($out) . ' (' . round(filesize($out) / 1024) . ' KB)');
        if ($this->option('files')) {
            $f = "$dir/files-$stamp.tar.gz";
            $p = new Process(['tar', '-czf', $f, '-C', storage_path('app'), '--exclude=backups', 'private', 'public']); $p->setTimeout(3600); $p->run();
            $p->isSuccessful() ? $this->info('Files backup: ' . basename($f)) : $this->warn('Files backup failed: ' . trim($p->getErrorOutput()));
        }
        $keep = (int) env('BACKUP_KEEP_DAYS', 14); $n = 0;
        foreach (glob("$dir/*") ?: [] as $old) if (is_file($old) && filemtime($old) < now()->subDays($keep)->timestamp) { unlink($old); $n++; }
        if ($n) $this->line("Deleted $n backup(s) older than $keep days.");
        return self::SUCCESS;
    }
}
