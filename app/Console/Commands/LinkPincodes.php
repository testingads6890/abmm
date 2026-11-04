<?php

namespace App\Console\Commands;

use App\Models\Lookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** php artisan abmm:link-pincodes : sets each tehsil's pincode from database/data/tehsil_pincodes.json (LGD). Re-runnable. */
class LinkPincodes extends Command
{
    protected $signature = 'abmm:link-pincodes';
    protected $description = 'Fill the pincode of each tehsil (used by the family form)';

    public function handle(): int
    {
        $rows = json_decode((string) file_get_contents(database_path('data/tehsil_pincodes.json')), true)['tehsils'] ?? [];
        $done = 0;
        foreach ($rows as $r) {
            $n = DB::table('lookups as t')
                ->join('lookups as d', 'd.id', '=', 't.parent_id')
                ->join('lookups as s', 's.id', '=', 'd.parent_id')
                ->where('t.type', 'tehsil')->where('t.name', $r['tehsil'])
                ->where('d.type', 'district')->where('d.name', $r['district'])
                ->where('s.type', 'state')->where('s.name', $r['state'])
                ->update(['t.pincode' => $r['pincode']]);
            $done += $n;
        }
        Lookup::flush();
        $this->info("Pincodes set on $done tehsil row(s).");
        return self::SUCCESS;
    }
}
