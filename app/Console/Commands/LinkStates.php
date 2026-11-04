<?php

namespace App\Console\Commands;

use App\Models\Lookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan abmm:link-states : sets each State's ABMM Pradesh (and so its Anchal) from database/data/anchal_state_map.json.
 * Only the State row that holds the districts is changed. Re-runnable.
 */
class LinkStates extends Command
{
    protected $signature = 'abmm:link-states';
    protected $description = 'Link each State to its ABMM Pradesh (Anchal follows)';

    public function handle(): int
    {
        $map = json_decode((string) file_get_contents(database_path('data/anchal_state_map.json')), true)['map'] ?? [];
        $failed = 0;
        foreach ($map as $state => $pradesh) {
            $pid = DB::table('lookups')->where('type', 'pradesh')->where('name', $pradesh)->value('id');
            if (! $pid) { $this->error("Pradesh not found: $pradesh"); $failed++; continue; }
            // The State row that has districts under it is the one the form uses.
            $rows = DB::table('lookups as s')->where('s.type', 'state')->where('s.name', $state)
                ->select('s.id', 's.parent_id', DB::raw('(select count(*) from lookups d where d.type = "district" and d.parent_id = s.id) as districts'))
                ->get();
            $row = $rows->sortByDesc('districts')->first();
            if (! $row) { $this->error("State not found: $state"); $failed++; continue; }
            if ((int) $row->parent_id === (int) $pid) { $this->line("$state: already linked"); continue; }
            try {
                DB::table('lookups')->where('id', $row->id)->update(['parent_id' => $pid]);
                $this->line("$state -> $pradesh");
            } catch (\Throwable $e) {
                $this->error("$state: could not link (" . substr($e->getMessage(), 0, 80) . ')');
                $failed++;
            }
        }
        Lookup::flush();
        $this->info($failed ? "Done with $failed problem(s)." : 'All states linked.');
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
