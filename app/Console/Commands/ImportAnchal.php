<?php

namespace App\Console\Commands;

use App\Models\Lookup;
use Illuminate\Console\Command;

/**
 * php artisan abmm:import-anchal [file] : loads Anchal -> ABMM Pradesh mapping into Master Data (type anchal / pradesh).
 * Re-runnable: existing rows are matched by type + name and only their parent / active flag is set.
 * Default file: database/data/anchal_pradesh.json (converted from pradesh___anchal_data.xls).
 */
class ImportAnchal extends Command
{
    protected $signature = 'abmm:import-anchal {file? : JSON file of [{"anchal": "...", "pradesh": ["..."]}]}';
    protected $description = 'Import Anchal and its ABMM Pradesh list into Master Data';

    public function handle(): int
    {
        $path = $this->argument('file') ?: database_path('data/anchal_pradesh.json');
        if (! is_file($path)) {
            $this->error("File not found: $path");
            return self::FAILURE;
        }
        $groups = json_decode((string) file_get_contents($path), true);
        if (! is_array($groups)) {
            $this->error('Invalid JSON.');
            return self::FAILURE;
        }

        $sort = 0;
        foreach ($groups as $g) {
            $anchal = Lookup::updateOrCreate(['type' => 'anchal', 'name' => trim($g['anchal'])], ['is_active' => true, 'sort' => ++$sort]);
            foreach ($g['pradesh'] ?? [] as $p) {
                $name = trim((string) $p);
                if ($name === '') continue;
                Lookup::updateOrCreate(['type' => 'pradesh', 'name' => $name], ['parent_id' => $anchal->id, 'is_active' => true]);
            }
            $this->line("{$anchal->name}: " . count($g['pradesh'] ?? []) . ' pradesh');
        }
        Lookup::flush();
        $this->info('Anchal import done.');
        return self::SUCCESS;
    }
}
