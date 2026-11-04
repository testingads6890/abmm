<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ABMM Anchal -> Pradesh master data (Master Data > ABMM Anchal / ABMM Pradesh).
 * Safe to run again: finds existing rows by name (case-insensitive) and only links / adds what is missing.
 * Existing Pradesh values that are not in the list are left untouched (no Anchal).
 *   php artisan db:seed --class=AnchalSeeder
 */
class AnchalSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/anchal_pradesh.php');
        $now = now();

        $find = fn (string $type, string $name) => DB::table('lookups')->where('type', $type)->whereRaw('UPPER(TRIM(name)) = ?', [mb_strtoupper(trim($name))])->orderBy('id')->first();
        $nextSort = fn (string $type) => (int) DB::table('lookups')->where('type', $type)->max('sort') + 1;

        foreach (array_keys($data) as $i => $anchal) {
            $pradeshes = $data[$anchal];
            $a = $find('anchal', $anchal);
            $aid = $a ? $a->id : DB::table('lookups')->insertGetId(['type' => 'anchal', 'parent_id' => null, 'name' => $anchal, 'sort' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

            foreach ($pradeshes as $name) {
                $p = $find('pradesh', $name);
                if ($p) {
                    if ((int) $p->parent_id !== (int) $aid) DB::table('lookups')->where('id', $p->id)->update(['parent_id' => $aid, 'updated_at' => $now]);
                } else {
                    DB::table('lookups')->insert(['type' => 'pradesh', 'parent_id' => $aid, 'name' => $name, 'sort' => $nextSort('pradesh'), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }

        if (class_exists(\App\Models\Lookup::class)) \App\Models\Lookup::flush();
    }
}
