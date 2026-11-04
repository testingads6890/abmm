<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/** Location master tree: Country > State > District > City > Zone 1, Zone 2 ... > Pincode. One table, six levels. Additive; the existing `zones` table stays in sync. */
return new class extends Migration {
    private const INDIA = ['Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
        'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'];

    public function up(): void
    {
        Schema::create('geo_nodes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('parent_id')->nullable()->index();
            $t->string('level', 12);                      // country | state | district | city | zone | pincode
            $t->string('name', 150);                      // pincode level: the pincode itself
            $t->string('code', 150)->nullable();          // iso / short code; pincode level: area / locality name
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['level', 'name']);
        });

        $get = function (?int $parent, string $level, string $name): int {
            $q = DB::table('geo_nodes')->where(['level' => $level, 'name' => $name]);
            $parent === null ? $q->whereNull('parent_id') : $q->where('parent_id', $parent);
            $id = $q->value('id');
            return $id ?: DB::table('geo_nodes')->insertGetId(['parent_id' => $parent, 'level' => $level, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        };

        $india = $get(null, 'country', 'INDIA');
        foreach (self::INDIA as $i => $s) { $id = $get($india, 'state', $s); DB::table('geo_nodes')->where('id', $id)->update(['sort' => $i + 1]); }

        foreach (DB::table('country_settings')->pluck('country') as $c) $get(null, 'country', strtoupper($c));

        // carry over what already exists: zones and the places families live in
        $path = function (?string $country, ?string $state, ?string $district, ?string $city) use ($get): ?int {
            if (! $country || ! $city) return null;
            $p = $get(null, 'country', strtoupper(trim($country)));
            if ($state) $p = $get($p, 'state', trim($state));
            if ($district) $p = $get($p, 'district', trim($district));
            return $get($p, 'city', trim($city));
        };
        foreach (DB::table('zones')->get() as $z) { $c = $path($z->country, $z->state, $z->district, $z->city); if ($c) $get($c, 'zone', $z->name); }
        foreach (DB::table('families')->whereNotNull('city')->where('city', '!=', '')->select('country', 'pradesh', 'district', 'city')->distinct()->limit(5000)->get() as $f) $path($f->country ?: 'INDIA', $f->pradesh, $f->district, $f->city);
    }

    public function down(): void { Schema::dropIfExists('geo_nodes'); }
};
