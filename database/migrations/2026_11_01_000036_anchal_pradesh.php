<?php

use Database\Seeders\AnchalSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** ABMM Anchal level above ABMM Pradesh: 5 Anchals and their Pradesh sabhas, stored in `lookups` (type anchal; pradesh.parent_id -> anchal). No schema change. */
return new class extends Migration {
    public function up(): void
    {
        (new AnchalSeeder)->run();
    }

    public function down(): void
    {
        $ids = DB::table('lookups')->where('type', 'anchal')->pluck('id');
        DB::table('lookups')->where('type', 'pradesh')->whereIn('parent_id', $ids)->update(['parent_id' => null]);
        DB::table('lookups')->whereIn('id', $ids)->delete();
        if (class_exists(\App\Models\Lookup::class)) \App\Models\Lookup::flush();
    }
};
