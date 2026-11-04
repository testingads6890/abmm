<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Caste (Maheshwari) + Gotra masters (Super Admin manages them in Master Data; they appear as dropdowns for Sub-Admins and in the mobile app)
 * and a phone country code per family / member. Seeds Maheshwari + its gotras + dial codes of countries already in the Country master (idempotent).
 */
return new class extends Migration {
    /** Gotras of the Maheshwari samaj (62 gotra list published by the samaj). Super Admin can add / rename / deactivate from Master Data > Gotra. */
    private const GOTRAS = [
        'Dhumrans', 'Liyans', 'Silans', 'Sodhans', 'Kashyap', 'Nanasain', 'Dhanans', 'Karvans', 'Kapilans', 'Thevadans', 'Kagayans', 'Gautam', 'Balans', 'Gajans', 'Bhansali',
        'Attalans', 'Dhaulans', 'Jhumrans', 'Achitrans', 'Amrans', 'Rajhans', 'Dhalans', 'Haridrans', 'Kaushik', 'Manans', 'Bhattyans', 'Sasans', 'Phaphdans', 'Gaurans', 'Mugans',
        'Panchans', 'Jaisalani', 'Gauvans', 'Chandrans', 'Lorans', 'Musayans', 'Chudans', 'Bachhans', 'Bugdalimbh', 'Sadhans', 'Movanans', 'Sirsess', 'Paras', 'Kavalans', 'Makaranis',
        'Khalans', 'Nananans', 'Piplans', 'Gaukalayans', 'Nirmalans', 'Sanakas', 'Manamas', 'Shandilya', 'Malans', 'Dhamains', 'Chitrans', 'Hadans', 'Gaukanya', 'Bharadwaj', 'Babas',
        'Jaisalas', 'Kalans',
    ];

    private const DIAL = [
        'INDIA' => '+91', 'USA' => '+1', 'UNITED STATES' => '+1', 'UNITED STATES OF AMERICA' => '+1', 'CANADA' => '+1', 'UNITED KINGDOM' => '+44', 'UK' => '+44', 'UAE' => '+971',
        'UNITED ARAB EMIRATES' => '+971', 'SINGAPORE' => '+65', 'AUSTRALIA' => '+61', 'NEW ZEALAND' => '+64', 'NEPAL' => '+977', 'BHUTAN' => '+975', 'SRI LANKA' => '+94',
        'BANGLADESH' => '+880', 'PAKISTAN' => '+92', 'SAUDI ARABIA' => '+966', 'QATAR' => '+974', 'KUWAIT' => '+965', 'OMAN' => '+968', 'BAHRAIN' => '+973', 'MALAYSIA' => '+60',
        'THAILAND' => '+66', 'HONG KONG' => '+852', 'JAPAN' => '+81', 'GERMANY' => '+49', 'FRANCE' => '+33', 'ITALY' => '+39', 'SOUTH AFRICA' => '+27', 'KENYA' => '+254',
    ];

    public function up(): void
    {
        foreach (['families', 'members'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) use ($tbl) {
                $t->string('caste', 60)->nullable()->default('Maheshwari');
                $t->string('gotra', 80)->nullable()->index($tbl . '_gotra_idx');
                $t->string('country_code', 6)->default('+91');
            });
        }

        $now = now();
        // unique(type,name,parent_id) does not stop duplicates when parent_id is NULL, so check first.
        if (! DB::table('lookups')->where('type', 'caste')->where('name', 'Maheshwari')->exists()) {
            DB::table('lookups')->insert(['type' => 'caste', 'name' => 'Maheshwari', 'parent_id' => null, 'sort' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $casteId = DB::table('lookups')->where('type', 'caste')->where('name', 'Maheshwari')->value('id');
        $rows = [];
        foreach (self::GOTRAS as $i => $g) {
            $rows[] = ['type' => 'gotra', 'name' => $g, 'parent_id' => $casteId, 'sort' => $i, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($rows, 100) as $chunk) DB::table('lookups')->insertOrIgnore($chunk);

        // Dial code lives in lookups.meta for the Country master; make sure India exists and every known country carries its code.
        if (! DB::table('lookups')->where('type', 'country')->whereRaw('UPPER(name) = ?', ['INDIA'])->exists()) {
            DB::table('lookups')->insert(['type' => 'country', 'name' => 'INDIA', 'parent_id' => null, 'sort' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (DB::table('lookups')->where('type', 'country')->get(['id', 'name', 'meta']) as $c) {
            $dial = self::DIAL[strtoupper(trim($c->name))] ?? null;
            if ($dial && ! json_decode((string) $c->meta, true)) DB::table('lookups')->where('id', $c->id)->update(['meta' => json_encode(['dial' => $dial])]);
        }
        cache()->forget('api.masters');
        cache()->forget('lookups.grouped');
    }

    public function down(): void
    {
        foreach (['families', 'members'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) use ($tbl) {
                $t->dropIndex($tbl . '_gotra_idx');
                $t->dropColumn(['caste', 'gotra', 'country_code']);
            });
        }
        DB::table('lookups')->whereIn('type', ['gotra', 'caste'])->delete();
    }
};
