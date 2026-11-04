<?php

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

/** Loads the exact dropdown values copied from the live abmmsampark.org (database/seeders/data/*). Safe to re-run. */
class LookupSeeder extends Seeder
{
    private function add(string $type, string $name, ?int $parent = null, int $sort = 0, ?string $pincode = null): Lookup
    {
        return Lookup::firstOrCreate(['type' => $type, 'name' => $name, 'parent_id' => $parent], ['sort' => $sort, 'pincode' => $pincode, 'is_active' => true]);
    }

    private function bulk(string $type, array $names): void
    {
        $rows = []; $now = now();
        foreach (array_values(array_unique($names)) as $i => $n) {
            $rows[] = ['type' => $type, 'name' => $n, 'parent_id' => null, 'sort' => $i, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($rows, 500) as $chunk) Lookup::insertOrIgnore($chunk);   // unique(type,name,parent_id) keeps it idempotent
    }

    public function run(): void
    {
        $a = require __DIR__ . '/data/master_a.php';
        $b = require __DIR__ . '/data/master_b.php';
        $edu = array_merge(explode('|', trim(file_get_contents(__DIR__ . '/data/education_1.txt'))), explode('|', trim(file_get_contents(__DIR__ . '/data/education_2.txt'))));
        $city = [];
        foreach ([1, 2, 3] as $n) $city = array_merge($city, explode('|', trim(file_get_contents(__DIR__ . "/data/city_$n.txt"))));

        foreach ($a as $type => $v) $this->bulk($type, explode('|', $v));
        foreach ($b as $type => $v) { if ($type !== 'tehsil') $this->bulk($type, explode('|', $v)); }
        $this->bulk('education', $edu);
        $this->bulk('city', $city);
        $this->bulk('state', explode('|', trim(file_get_contents(__DIR__ . '/data/state_world.txt'))));   // live "Govt. State" list (India + overseas states/provinces)

        // Location hierarchy seen on the live site for the Jaipur sub-admin: Pradesh -> State -> District -> Tehsil/Town.
        // Add the rest (other pradesh / states / districts / pincodes) from Super Admin > Master Data.
        // a Pradesh may already sit under an Anchal (migration 2026_11_01_000036): reuse it whatever its parent
        $p = Lookup::where(['type' => 'pradesh', 'name' => 'PURVOTTAR RAJASTHAN PRADESH MAHESHWARI SABHA'])->orderBy('id')->first() ?? $this->add('pradesh', 'PURVOTTAR RAJASTHAN PRADESH MAHESHWARI SABHA');
        $s = $this->add('state', 'RAJASTHAN', $p->id);
        $d = $this->add('district', 'JAIPUR', $s->id);
        foreach (explode('|', $b['tehsil']) as $i => $t) $this->add('tehsil', $t, $d->id, $i);
        Lookup::flush();
    }
}
