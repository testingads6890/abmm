<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member-sheet import (one row per person, grouped by Family ID, as in the Bharatpur CSV).
 *  - Many real sheets have no mobile for the head of family -> families.mobile becomes optional.
 *  - Per-person columns that the sheet carries but the schema lacked: mediclaim, Pakshik membership,
 *    "profile last updated", tehsil on members.
 *  - import_batches keeps a history of each import so a wrong file can be undone.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('file_name')->nullable();
            $t->unsignedInteger('rows')->default(0);
            $t->unsignedInteger('families_created')->default(0);
            $t->unsignedInteger('families_updated')->default(0);
            $t->unsignedInteger('members_created')->default(0);
            $t->unsignedInteger('members_updated')->default(0);
            $t->unsignedInteger('skipped')->default(0);
            $t->json('warnings')->nullable();
            $t->timestamp('undone_at')->nullable();
            $t->timestamps();
        });

        Schema::table('families', function (Blueprint $t) {
            $t->string('mobile', 15)->nullable()->change();
            $t->date('profile_updated_on')->nullable();
            $t->unsignedBigInteger('import_batch_id')->nullable()->index();
        });

        Schema::table('members', function (Blueprint $t) {
            $t->boolean('has_mediclaim')->nullable();
            $t->boolean('pakshik_member')->nullable();
            $t->date('profile_updated_on')->nullable();
            $t->string('tehsil')->nullable();
            $t->unsignedBigInteger('import_batch_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn(['has_mediclaim', 'pakshik_member', 'profile_updated_on', 'tehsil', 'import_batch_id']));
        Schema::table('families', fn (Blueprint $t) => $t->dropColumn(['profile_updated_on', 'import_batch_id']));
        Schema::dropIfExists('import_batches');
    }
};
