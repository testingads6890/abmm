<?php

namespace App\Console\Commands;

use App\Services\LegacyImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan abmm:import-check [families.csv] [members.csv] [--commit] [--audit]
 * Step 1 (default): DRY RUN of the real old-system CSV export - nothing is saved; prints created / updated / skipped and the first errors.
 * Step 2: add --commit to really import. Step 3: --audit reconciles the database (duplicate mobiles, families without a head, member-count mismatches).
 */
class ImportCheck extends Command
{
    protected $signature = 'abmm:import-check {families? : families CSV} {members? : members CSV} {--commit} {--audit}';
    protected $description = 'Dry-run / import the old system CSV export and audit the result';

    public function handle(): int
    {
        $dry = ! $this->option('commit'); $bad = 0;
        foreach (['families' => 'importFamilies', 'members' => 'importMembers'] as $arg => $fn) {
            $path = $this->argument($arg); if (! $path) continue;
            if (! is_file($path)) { $this->error("File not found: $path"); $bad++; continue; }
            $imp = new LegacyImporter();
            try { $s = $imp->$fn($path, $dry); } catch (\Throwable $e) { $this->error(ucfirst($arg) . ': ' . $e->getMessage()); $bad++; continue; }
            $this->info(($dry ? '[DRY RUN] ' : '') . ucfirst($arg) . ": created {$s['created']}, updated {$s['updated']}, skipped {$s['skipped']}");
            foreach (array_slice($imp->errors, 0, 15) as $e) $this->line('  - ' . (is_array($e) ? json_encode($e) : $e));
            if (count($imp->errors) > 15) $this->line('  ... and ' . (count($imp->errors) - 15) . ' more');
            if ($s['skipped']) $bad++;
        }
        if ($this->option('audit') || $this->option('commit')) $this->audit();
        if ($dry && ($this->argument('families') || $this->argument('members'))) $this->comment('Nothing was saved. Fix the listed rows in the CSV, then run again with --commit.');
        return $bad ? self::FAILURE : self::SUCCESS;
    }

    private function audit(): void
    {
        $f = DB::table('families')->count(); $m = DB::table('members')->count();
        $this->info("Database now: $f families, $m members");
        $dup = DB::table('families')->select('mobile', DB::raw('count(*) as c'))->groupBy('mobile')->having('c', '>', 1)->get();
        $this->line('Mobiles used by more than one family: ' . $dup->count() . ($dup->count() ? ' (e.g. ' . $dup->take(5)->pluck('mobile')->implode(', ') . ')' : ''));
        $nohead = DB::table('families')->whereNotIn('id', DB::table('members')->where('is_head', true)->select('family_id'))->count();
        $this->line("Families without a head member: $nohead");
        $mis = DB::table('families as f')->leftJoin(DB::raw('(select family_id, count(*) as n from members group by family_id) x'), 'x.family_id', '=', 'f.id')
            ->whereRaw('coalesce(x.n,0) <> f.member_count')->count();
        $this->line("Families whose member_count differs from the members actually stored: $mis");
        $nopr = DB::table('families')->where(fn ($q) => $q->whereNull('pradesh')->orWhere('pradesh', ''))->count();
        $this->line("Families without a Pradesh (state sub-admins cannot see them): $nopr");
        $nocity = DB::table('families')->where(fn ($q) => $q->whereNull('city')->orWhere('city', ''))->count();
        $this->line("Families without a city: $nocity");
    }
}
