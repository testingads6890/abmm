<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/** Milestone B: polls & surveys, event RSVP + QR pass, streaks, points/badges/rewards/certificates, data-correction requests, notification filters. Additive only. */
return new class extends Migration {
    public function up(): void
    {
        // ---- notification filters + health flags used for targeting
        Schema::table('notifications_log', function (Blueprint $t) { $t->json('filters')->nullable(); });
        Schema::table('members', function (Blueprint $t) {
            $t->boolean('vaccinated')->nullable();
            $t->boolean('covid_recovered')->nullable();
        });

        // ---- streak & active-today
        Schema::create('member_streaks', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->primary();
            $t->unsignedInteger('current')->default(0);
            $t->unsignedInteger('longest')->default(0);
            $t->date('last_date')->nullable();
            $t->timestamps();
        });
        Schema::create('daily_active', function (Blueprint $t) {
            $t->id();
            $t->date('day');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('family_id')->nullable();
            $t->string('country', 100)->nullable();
            $t->string('city', 150)->nullable();
            $t->unique(['day', 'user_id']);
            $t->index(['day', 'country']);
        });

        // ---- polls & surveys
        Schema::create('polls', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 8)->default('poll');          // poll | survey
            $t->string('title', 180);
            $t->text('description')->nullable();
            $t->json('target')->nullable();                  // {country,pradesh,state,district,city,zone}; empty = everyone
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('show_results')->default(true);      // members may see aggregate results after voting
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('poll_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
            $t->unsignedInteger('sort')->default(0);
            $t->string('text', 300);
            $t->string('type', 8);                           // single | multi | text | rating
            $t->json('options')->nullable();
            $t->boolean('required')->default(true);
        });
        Schema::create('poll_responses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('family_id')->nullable();
            $t->string('country', 100)->nullable();
            $t->string('state', 150)->nullable();
            $t->string('district', 150)->nullable();
            $t->string('city', 150)->nullable();
            $t->json('answers');                             // {question_id: value|[values]}
            $t->timestamps();
            $t->unique(['poll_id', 'user_id']);
        });

        // ---- event RSVP + QR pass
        Schema::table('events', function (Blueprint $t) {
            $t->boolean('rsvp_enabled')->default(false);
            $t->unsignedInteger('capacity')->nullable();
            $t->unsignedTinyInteger('max_guests')->default(4);
            $t->date('rsvp_closes_on')->nullable();
            $t->unsignedInteger('attend_points')->nullable();
        });
        Schema::create('event_rsvps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('family_id')->nullable();
            $t->unsignedTinyInteger('guests')->default(0);
            $t->string('status', 8)->default('going');       // going | declined
            $t->string('pass_code', 16)->unique();
            $t->timestamp('checked_in_at')->nullable();
            $t->unsignedBigInteger('checked_by')->nullable();
            $t->timestamps();
            $t->unique(['event_id', 'user_id']);
        });

        // ---- points, badges, rewards, certificates
        Schema::create('point_rules', function (Blueprint $t) {
            $t->id();
            $t->string('action', 40)->unique();
            $t->string('label', 120);
            $t->integer('points');
            $t->unsignedInteger('daily_cap')->nullable();    // max awards per user per day (null = unlimited)
            $t->boolean('is_active')->default(true);
        });
        Schema::create('point_ledger', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('family_id')->nullable();
            $t->string('country', 100)->nullable();
            $t->string('city', 150)->nullable();
            $t->string('zone', 100)->nullable();
            $t->string('action', 40);
            $t->string('ref', 60)->nullable();
            $t->integer('points');
            $t->string('note', 160)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['user_id', 'action', 'ref'], 'ledger_once');
            $t->index(['created_at', 'country']);
        });
        Schema::create('badges', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name', 80);
            $t->string('description', 200)->nullable();
            $t->unsignedInteger('min_points')->nullable();  // auto-awarded at this lifetime total (null = manual / recognition)
            $t->boolean('is_active')->default(true);
        });
        Schema::create('user_badges', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->foreignId('badge_id')->constrained('badges')->cascadeOnDelete();
            $t->string('ref', 20)->nullable();               // e.g. month for star of the month
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['user_id', 'badge_id', 'ref']);
        });
        Schema::create('rewards', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->text('description')->nullable();
            $t->unsignedInteger('cost')->default(100);
            $t->unsignedInteger('stock')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reward_id')->constrained('rewards');
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('family_id')->nullable();
            $t->unsignedInteger('points');
            $t->string('status', 10)->default('pending');   // pending | fulfilled | rejected
            $t->string('remark', 200)->nullable();
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->timestamps();
        });
        Schema::create('certificates', function (Blueprint $t) {
            $t->id();
            $t->string('number', 24)->unique();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('family_id')->nullable();
            $t->string('type', 20);                          // star_of_month | event | achievement
            $t->string('title', 180);
            $t->string('detail', 255)->nullable();
            $t->string('ref', 30)->nullable();
            $t->string('path', 255)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'type', 'ref'], 'cert_once');
        });

        // ---- data-correction requests
        Schema::create('data_corrections', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('family_id')->index();
            $t->unsignedBigInteger('member_id')->nullable();
            $t->unsignedBigInteger('user_id');
            $t->string('target', 8);                         // family | member
            $t->string('field', 40);
            $t->text('old_value')->nullable();
            $t->text('new_value');
            $t->string('reason', 255)->nullable();
            $t->string('status', 10)->default('pending');   // pending | approved | rejected
            $t->string('remark', 255)->nullable();
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });

        // ---- defaults
        DB::table('point_rules')->insert([
            ['action' => 'daily_open', 'label' => 'Open the app today', 'points' => 2, 'daily_cap' => 1, 'is_active' => true],
            ['action' => 'streak_7', 'label' => '7-day streak bonus', 'points' => 20, 'daily_cap' => null, 'is_active' => true],
            ['action' => 'streak_30', 'label' => '30-day streak bonus', 'points' => 100, 'daily_cap' => null, 'is_active' => true],
            ['action' => 'poll_vote', 'label' => 'Vote in a poll', 'points' => 5, 'daily_cap' => 5, 'is_active' => true],
            ['action' => 'survey_complete', 'label' => 'Complete a survey', 'points' => 10, 'daily_cap' => 3, 'is_active' => true],
            ['action' => 'event_rsvp', 'label' => 'RSVP to an event', 'points' => 3, 'daily_cap' => 5, 'is_active' => true],
            ['action' => 'event_attend', 'label' => 'Attend an event (QR check-in)', 'points' => 20, 'daily_cap' => null, 'is_active' => true],
            ['action' => 'form_submit', 'label' => 'Submit an online form', 'points' => 5, 'daily_cap' => 3, 'is_active' => true],
            ['action' => 'referral', 'label' => 'Refer a new family', 'points' => 50, 'daily_cap' => null, 'is_active' => true],
            ['action' => 'quiz_complete', 'label' => 'Complete a weekly quiz', 'points' => 10, 'daily_cap' => 3, 'is_active' => true],
            ['action' => 'housie_play', 'label' => 'Play weekly housie', 'points' => 5, 'daily_cap' => 1, 'is_active' => true],
        ]);
        DB::table('badges')->insert([
            ['code' => 'bronze', 'name' => 'Bronze Member', 'description' => 'Earned 100 points', 'min_points' => 100, 'is_active' => true],
            ['code' => 'silver', 'name' => 'Silver Member', 'description' => 'Earned 500 points', 'min_points' => 500, 'is_active' => true],
            ['code' => 'gold', 'name' => 'Gold Member', 'description' => 'Earned 1500 points', 'min_points' => 1500, 'is_active' => true],
            ['code' => 'star_of_month', 'name' => 'Star of the Month', 'description' => 'Top performer of a month', 'min_points' => null, 'is_active' => true],
        ]);
    }

    public function down(): void
    {
        foreach (['data_corrections', 'certificates', 'redemptions', 'rewards', 'user_badges', 'badges', 'point_ledger', 'point_rules', 'event_rsvps', 'poll_responses', 'poll_questions', 'polls', 'daily_active', 'member_streaks'] as $t) Schema::dropIfExists($t);
        Schema::table('events', function (Blueprint $t) { $t->dropColumn(['rsvp_enabled', 'capacity', 'max_guests', 'rsvp_closes_on', 'attend_points']); });
        Schema::table('members', function (Blueprint $t) { $t->dropColumn(['vaccinated', 'covid_recovered']); });
        Schema::table('notifications_log', function (Blueprint $t) { $t->dropColumn('filters'); });
    }
};
