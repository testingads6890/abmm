<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Subh Sandesh (one message per day, optionally per pradesh) and the festival calendar. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_messages', function (Blueprint $t) {
            $t->id();
            $t->date('show_on');
            $t->string('pradesh')->nullable();            // null = all India
            $t->string('tithi', 120)->nullable();
            $t->text('body_hi');
            $t->text('body_en')->nullable();
            $t->string('image')->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['show_on', 'pradesh'], 'daily_day_pradesh_uq');
        });

        Schema::create('festivals', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->date('festival_date')->index();
            $t->text('message');
            $t->string('banner')->nullable();
            $t->string('pradesh')->nullable();            // null = all India
            $t->boolean('notify')->default(true);         // push on the day
            $t->boolean('is_active')->default(true);
            $t->timestamp('notified_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festivals');
        Schema::dropIfExists('daily_messages');
    }
};
