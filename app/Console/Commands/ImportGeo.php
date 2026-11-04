<?php

namespace App\Console\Commands;

use App\Models\Lookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * php artisan abmm:import-geo [file] : loads India State -> District -> Tehsil (subdistrict) into Master Data.
 * Re-runnable: existing rows are matched by type + name (+ parent), so nothing is duplicated.
 * Default file: database/data/india_geo.json (from the LGD open data). Tehsil chain: tehsil -> district -> state.
 */
class ImportGeo extends Command
{
    protected $signature = 'abmm:import-geo {file? : JSON file produced from the LGD data}';
    protected $description = 'Import India states, districts and tehsils into Master Data';

    public function handle(): int
    {
        $path = $this->argument('file') ?: database_path('data/india_geo.json');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($data['states'] ?? null)) {
            $this->error("Invalid or missing file: $path");
            return self::FAILURE;
        }
        $now = now();
        $stamp = Schema::hasColumn('lookups', 'created_at') ? ['created_at' => $now, 'updated_at' => $now] : [];

        foreach ($data['states'] as $s) {
            $state = $this->findOrMake('state', $s['state'], null, $stamp);
            foreach ($s['districts'] as $d) {
                $district = $this->findOrMake('district', $d['name'], $state->id, $stamp);
                $have = Lookup::ofType('tehsil')->where('parent_id', $district->id)->pluck('name')->flip();
                $rows = [];
                foreach ($d['tehsils'] as $t) {
                    if ($have->has($t) || $t === '') continue;
                    $rows[] = ['type' => 'tehsil', 'name' => $t, 'parent_id' => $district->id, 'is_active' => true] + $stamp;
                    $have[$t] = true;
                }
                foreach (array_chunk($rows, 500) as $chunk) Lookup::insert($chunk);
            }
            $this->line("{$s['state']}: " . count($s['districts']) . ' districts');
        }
        Lookup::flush();
        $this->info('Geo import done.');
        return self::SUCCESS;
    }

    /** Existing row of this type and name (and parent, when given); a row with a parent is preferred, else create. */
    private function findOrMake(string $type, string $name, ?int $parent, array $stamp): Lookup
    {
        $q = Lookup::ofType($type)->where('name', $name);
        if ($parent !== null) $q->where('parent_id', $parent);
        $found = $q->orderByRaw('parent_id IS NULL')->first();
        if ($found) {
            if ($parent !== null && $found->parent_id !== $parent) $found->update(['parent_id' => $parent]);
            return $found;
        }
        return Lookup::create(['type' => $type, 'name' => $name, 'parent_id' => $parent, 'is_active' => true]);
    }
}
