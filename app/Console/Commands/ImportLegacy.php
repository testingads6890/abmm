<?php

namespace App\Console\Commands;

use App\Services\LegacyImporter;
use Illuminate\Console\Command;

class ImportLegacy extends Command
{
    protected $signature = 'abmm:import {type : families|members} {file : CSV path} {--dry-run : Validate and roll back}';
    protected $description = 'Import a CSV export from the old ABMM Sampark system';

    public function handle(): int
    {
        $imp = new LegacyImporter();
        $stats = $this->argument('type') === 'members'
            ? $imp->importMembers($this->argument('file'), $this->option('dry-run'))
            : $imp->importFamilies($this->argument('file'), $this->option('dry-run'));
        $this->table(['created', 'updated', 'skipped'], [$stats]);
        foreach (array_slice($imp->errors, 0, 20) as $e) $this->warn($e);
        if ($this->option('dry-run')) $this->info('Dry-run: nothing was saved.');
        return self::SUCCESS;
    }
}
