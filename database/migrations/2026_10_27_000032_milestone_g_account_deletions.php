<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Milestone G: in-app account deletion (required by Google Play and the App Store). 30-day grace, then personal data is erased. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_deletions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->string('reason', 300)->nullable();
            $t->string('status', 10)->default('requested')->index();    // requested | erased | cancelled
            $t->dateTime('requested_at');
            $t->dateTime('erase_after');
            $t->dateTime('erased_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('account_deletions'); }
};
