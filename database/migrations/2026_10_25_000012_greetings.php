<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Birthday / anniversary wishes: indexed month-day columns (fast "today" lookup at 10 lakh members), privacy flag, wish + send logs. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $t) {
            $t->boolean('hide_birthday')->default(false);
            // MMDD virtual columns -> an index lookup instead of scanning every member each day.
            $t->string('dob_md', 4)->nullable()->virtualAs("CONCAT(LPAD(MONTH(dob), 2, '0'), LPAD(DAY(dob), 2, '0'))")->index('mem_dob_md_idx');
            $t->string('anniversary_md', 4)->nullable()->virtualAs("CONCAT(LPAD(MONTH(anniversary), 2, '0'), LPAD(DAY(anniversary), 2, '0'))")->index('mem_anniv_md_idx');
        });

        // One wish per (kind, from user, to member, year).
        Schema::create('greeting_wishes', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 12);
            $t->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('to_member_id')->index();
            $t->unsignedSmallInteger('year');
            $t->string('message', 200)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['kind', 'from_user_id', 'to_member_id', 'year'], 'wish_once_idx');
        });

        // Daily scheduler guard: never greet the same member twice in a year.
        Schema::create('greeting_logs', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 12);
            $t->unsignedBigInteger('member_id');
            $t->unsignedSmallInteger('year');
            $t->timestamp('created_at')->nullable();
            $t->unique(['kind', 'member_id', 'year'], 'greet_once_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('greeting_logs');
        Schema::dropIfExists('greeting_wishes');
        Schema::table('members', function (Blueprint $t) {
            $t->dropIndex('mem_dob_md_idx');
            $t->dropIndex('mem_anniv_md_idx');
            $t->dropColumn(['hide_birthday', 'dob_md', 'anniversary_md']);
        });
    }
};
