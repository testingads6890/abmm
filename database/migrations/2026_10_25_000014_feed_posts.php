<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Community feed: member achievements (moderated) and, later, admin news / condolence notices share one table. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('feed_posts', function (Blueprint $t) {
            $t->id();
            $t->string('type', 15)->default('achievement');      // achievement | news | condolence
            $t->string('category', 30)->nullable();
            $t->string('title', 200);
            $t->text('body')->nullable();
            $t->string('image')->nullable();                      // public disk
            $t->string('proof')->nullable();                      // private disk: marksheet / certificate, never shown in the app
            $t->boolean('proof_verified')->default(false);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();   // who submitted
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->unsignedBigInteger('member_id')->nullable()->index();                     // who achieved
            $t->string('pradesh')->nullable();
            $t->string('state')->nullable();
            $t->string('district')->nullable();
            $t->string('status', 12)->default('pending');         // pending | approved | rejected | changes
            $t->string('review_note', 300)->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->boolean('pinned')->default(false);
            $t->timestamp('publish_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->unsignedInteger('likes_count')->default(0);
            $t->timestamps();
            $t->index(['status', 'type', 'publish_at'], 'feed_live_idx');
            $t->index(['pradesh', 'status'], 'feed_area_idx');
            $t->index(['district', 'status'], 'feed_district_idx');
        });

        Schema::create('feed_likes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('feed_posts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->unique(['post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_likes');
        Schema::dropIfExists('feed_posts');
    }
};
