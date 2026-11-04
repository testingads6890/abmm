<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/** Country / city / zone on member-generated content so Country Admin, City Admin and Zone Coordinator can moderate it. Additive; existing rows are back-filled from the family. */
return new class extends Migration {
    private const TABLES = ['jobs_board' => 'jobs_area2_idx', 'job_profiles' => 'jprof_area2_idx', 'help_requests' => 'help_area2_idx', 'feed_posts' => 'feed_area2_idx'];

    public function up(): void
    {
        foreach (self::TABLES as $table => $idx) {
            if (! Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) use ($table, $idx) {
                if (! Schema::hasColumn($table, 'country')) $t->string('country', 100)->nullable();
                if (! Schema::hasColumn($table, 'city')) $t->string('city', 150)->nullable();
                if (! Schema::hasColumn($table, 'zone')) $t->string('zone', 100)->nullable();
                $t->index(['country', 'city', 'zone'], $idx);
            });
            $fk = Schema::hasColumn($table, 'family_id') ? 'family_id' : null;
            if ($fk) foreach (['country', 'city', 'zone'] as $c) {
                DB::statement("UPDATE `$table` SET `$c` = (SELECT `$c` FROM `families` WHERE `families`.`id` = `$table`.`family_id`) WHERE `family_id` IS NOT NULL AND `$c` IS NULL");
            }
            if (! $fk && Schema::hasColumn($table, 'user_id')) foreach (['country', 'city', 'zone'] as $c) {
                DB::statement("UPDATE `$table` SET `$c` = (SELECT f.`$c` FROM `families` f JOIN `users` u ON u.family_id = f.id WHERE u.id = `$table`.`user_id`) WHERE `$c` IS NULL");
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $idx) {
            if (! Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) use ($idx) { $t->dropIndex($idx); $t->dropColumn(['country', 'city', 'zone']); });
        }
    }
};
