<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Scheduled news: remember who to push to, and whether the push already went out. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('feed_posts', function (Blueprint $t) {
            $t->string('notify_audience', 10)->nullable();   // none | district | state | all
            $t->timestamp('notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('feed_posts', fn (Blueprint $t) => $t->dropColumn(['notify_audience', 'notified_at']));
    }
};
