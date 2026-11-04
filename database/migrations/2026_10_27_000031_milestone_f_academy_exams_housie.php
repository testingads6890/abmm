<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/** Milestone F: Learning Academy (courses, short videos, live classes, library), weekly MCQ exams, weekly Housie. */
return new class extends Migration {
    private function area(Blueprint $t): void
    {
        $t->string('country', 100)->nullable()->index();
        $t->string('pradesh', 100)->nullable()->index();
        $t->string('district', 100)->nullable();
        $t->string('city', 150)->nullable();
        $t->string('zone', 100)->nullable();
    }

    public function up(): void
    {
        /* ------------------------------------------------ Academy */
        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('segment', 10)->default('general');               // school | college | business | family | general
            $t->string('category', 60)->nullable();
            $t->text('description')->nullable();
            $t->string('thumbnail', 300)->nullable();
            $t->unsignedBigInteger('instructor_user_id')->nullable()->index();
            $t->string('instructor_name', 120)->nullable();
            $t->decimal('instructor_share_pct', 5, 2)->default(0);      // % of the course sale paid to the instructor
            $t->decimal('price', 10, 2)->default(0);                     // 0 = not sold separately (free or subscription only)
            $t->string('currency', 3)->default('INR');
            $t->boolean('free')->default(false);                         // whole course free
            $t->boolean('subscription')->default(true);                  // included in the "training" subscription
            $t->string('lang', 20)->default('Hinglish');
            $t->boolean('ai_generated')->default(false);
            $t->unsignedInteger('enrolments')->default(0);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->string('status', 10)->default('draft')->index();        // draft | live | archived
            $t->timestamps();
        });
        Schema::create('lessons', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('course_id')->index();
            $t->string('title');
            $t->string('provider', 10)->default('youtube');              // youtube | vimeo | mp4 | ai
            $t->string('video_url', 400)->nullable();
            $t->unsignedSmallInteger('duration_sec')->default(0);
            $t->boolean('free_preview')->default(false);
            $t->text('summary')->nullable();
            $t->text('script')->nullable();                              // AI-drafted narration script / transcript
            $t->unsignedSmallInteger('sort')->default(0);
            $t->string('status', 10)->default('live');
            $t->timestamps();
        });
        Schema::create('lesson_progress', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('lesson_id');
            $t->unsignedBigInteger('course_id')->index();
            $t->unsignedTinyInteger('percent')->default(0);
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'lesson_id']);
        });
        Schema::create('course_enrolments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('course_id');
            $t->unsignedBigInteger('user_id');
            $t->string('source', 12)->default('free');                   // free | purchase | subscription
            $t->unsignedBigInteger('payment_id')->nullable();
            $t->string('status', 14)->default('active');                 // awaiting_payment | active | cancelled
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['course_id', 'user_id']);
        });
        Schema::create('instructor_earnings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('course_id')->index();
            $t->unsignedBigInteger('instructor_user_id')->index();
            $t->unsignedBigInteger('payment_id')->unique();
            $t->decimal('gross', 10, 2);
            $t->decimal('share_pct', 5, 2);
            $t->decimal('amount', 10, 2);
            $t->string('status', 10)->default('pending');               // pending | paid | reversed
            $t->timestamps();
        });
        Schema::create('live_classes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('course_id')->nullable()->index();
            $t->string('title');
            $t->string('segment', 10)->default('general');
            $t->string('host', 120)->nullable();
            $t->string('provider', 8)->default('zoom');                  // zoom | meet
            $t->string('join_url', 400);
            $t->dateTime('starts_at');
            $t->unsignedSmallInteger('duration_min')->default(60);
            $t->boolean('subscribers_only')->default(false);
            $t->boolean('reminded')->default(false);
            $t->string('status', 10)->default('scheduled');            // scheduled | done | cancelled
            $t->timestamps();
        });
        Schema::create('live_class_joins', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('class_id');
            $t->unsignedBigInteger('user_id');
            $t->timestamp('joined_at')->useCurrent();
            $t->unique(['class_id', 'user_id']);
        });
        Schema::create('library_items', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('kind', 10)->default('pdf');                      // pdf | article | link | video
            $t->string('segment', 10)->default('general');
            $t->string('category', 60)->nullable();
            $t->string('url', 400);
            $t->text('description')->nullable();
            $t->unsignedInteger('views')->default(0);
            $t->string('status', 10)->default('live');
            $t->timestamps();
        });

        /* ------------------------------------------------ Exams */
        Schema::create('learner_profiles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->unique();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->string('segment', 10)->default('family');                // school | college | business | family
            $t->string('class_name', 60)->nullable();
            $t->string('institute', 150)->nullable();
            $this->area($t);
            $t->timestamps();
        });
        Schema::create('exam_questions', function (Blueprint $t) {
            $t->id();
            $t->string('segment', 10)->index();                          // school | college | business | family
            $t->string('subject', 80)->nullable();
            $t->text('question');
            $t->json('options');                                         // ["A text","B text",...]
            $t->unsignedTinyInteger('correct');                          // index into options
            $t->text('explanation')->nullable();
            $t->string('difficulty', 8)->default('medium');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('exams', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('segment', 10)->index();
            $t->string('kind', 8)->default('weekly');                    // weekly | special
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->unsignedSmallInteger('duration_min')->default(20);
            $t->unsignedSmallInteger('pass_pct')->default(40);
            $t->json('question_ids')->nullable();
            $t->string('status', 10)->default('draft')->index();        // draft | live | closed
            $t->boolean('results_sent')->default(false);
            $t->timestamps();
        });
        Schema::create('exam_attempts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('exam_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('family_id')->nullable();
            $t->dateTime('started_at');
            $t->dateTime('submitted_at')->nullable();
            $t->json('answers')->nullable();
            $t->unsignedSmallInteger('score')->nullable();
            $t->unsignedSmallInteger('total')->nullable();
            $t->decimal('percent', 5, 2)->nullable();
            $t->unsignedSmallInteger('seconds')->nullable();
            $t->unsignedInteger('rank')->nullable();
            $this->area($t);
            $t->timestamps();
            $t->unique(['exam_id', 'user_id']);
        });

        /* ------------------------------------------------ Housie */
        Schema::create('housie_sponsors', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('banner_url', 300)->nullable();
            $t->string('link_url', 300)->nullable();
            $t->string('status', 10)->default('active');
            $t->timestamps();
        });
        Schema::create('housie_games', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->dateTime('starts_at');
            $t->decimal('ticket_price', 10, 2)->default(0);
            $t->string('currency', 3)->default('INR');
            $t->unsignedTinyInteger('max_tickets')->default(4);          // per member
            $t->unsignedSmallInteger('interval_sec')->default(20);
            $t->json('prizes');                                          // {"early5":"...","line1":"...","line2":"...","line3":"...","full":"..."}
            $t->unsignedBigInteger('sponsor_id')->nullable();
            $t->json('sequence')->nullable();                            // full shuffled 1-90, never sent to clients
            $t->json('called')->nullable();                              // numbers called so far, in order
            $t->dateTime('last_called_at')->nullable();
            $t->string('status', 10)->default('scheduled')->index();    // scheduled | running | finished | cancelled
            $t->timestamps();
        });
        Schema::create('housie_tickets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('game_id');
            $t->unsignedBigInteger('user_id')->index();
            $t->json('grid');                                            // 3 rows x 9 columns, 0 = blank
            $t->unsignedBigInteger('payment_id')->nullable();
            $t->string('status', 10)->default('paid');                   // pending | paid | cancelled
            $t->timestamps();
            $t->index(['game_id', 'user_id']);
        });
        Schema::create('housie_claims', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('game_id');
            $t->unsignedBigInteger('ticket_id');
            $t->unsignedBigInteger('user_id');
            $t->string('prize', 8);                                      // early5 | line1 | line2 | line3 | full
            $t->string('status', 8);                                     // won | invalid
            $t->timestamps();
            $t->index(['game_id', 'prize']);
        });

        foreach ([['lesson_done', 'Complete a lesson', 2, 10], ['course_done', 'Complete a course', 25, null], ['exam_pass', 'Pass a weekly exam', 15, 3], ['housie_win', 'Win a Housie prize', 20, null]] as [$a, $l, $p, $cap]) {
            DB::table('point_rules')->updateOrInsert(['action' => $a], ['label' => $l, 'points' => $p, 'daily_cap' => $cap, 'is_active' => true]);
        }
    }

    public function down(): void
    {
        foreach (['housie_claims', 'housie_tickets', 'housie_games', 'housie_sponsors', 'exam_attempts', 'exams', 'exam_questions', 'learner_profiles', 'library_items', 'live_class_joins', 'live_classes',
            'instructor_earnings', 'course_enrolments', 'lesson_progress', 'lessons', 'courses'] as $tb) Schema::dropIfExists($tb);
        DB::table('point_rules')->whereIn('action', ['lesson_done', 'course_done', 'exam_pass', 'housie_win'])->delete();
    }
};
