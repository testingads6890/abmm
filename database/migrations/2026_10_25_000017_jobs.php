<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Community job portal: employers post openings (admin approves), job seekers keep a profile, apply in one tap and track status. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('jobs_board', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();   // the employer / recruiter
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->unsignedBigInteger('member_id')->nullable();
            $t->string('pradesh')->nullable();
            $t->string('state')->nullable();
            $t->string('district')->nullable();
            $t->string('job_type', 12)->default('full_time');       // full_time | part_time | internship | freelance
            $t->string('title', 160);
            $t->string('company', 160)->nullable();
            $t->string('city', 100)->nullable();
            $t->string('salary', 80)->nullable();
            $t->string('experience', 40)->nullable();               // "Fresher", "2-5 saal" ...
            $t->string('skills', 300)->nullable();                  // comma separated
            $t->unsignedSmallInteger('vacancies')->default(1);
            $t->text('description');
            $t->string('contact_name', 100)->nullable();
            $t->string('contact_phone', 20);
            $t->string('status', 10)->default('pending');           // pending | live | rejected | closed
            $t->string('admin_note', 300)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->unsignedInteger('views')->default(0);
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'expires_at'], 'jobs_live_idx');
            $t->index(['pradesh', 'status'], 'jobs_area_idx');
            $t->index(['user_id', 'status'], 'jobs_user_idx');
        });

        // One reusable profile ("resume") per job seeker.
        Schema::create('job_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('member_id')->nullable();
            $t->string('pradesh')->nullable();
            $t->string('district')->nullable();
            $t->string('name', 120);
            $t->string('headline', 120);                            // "B.Com graduate, Tally & GST"
            $t->string('education', 150)->nullable();
            $t->unsignedTinyInteger('experience_years')->default(0);
            $t->string('skills', 300)->nullable();
            $t->string('city', 100)->nullable();
            $t->string('job_type', 12)->nullable();                 // preferred type
            $t->string('expected_salary', 60)->nullable();
            $t->text('about')->nullable();
            $t->string('phone', 20);
            $t->string('resume_url', 300)->nullable();              // optional link (Drive / LinkedIn)
            $t->boolean('open_to_work')->default(true);             // visible to employers in "Candidates"
            $t->timestamps();
            $t->index(['open_to_work', 'city'], 'jp_open_idx');
        });

        Schema::create('job_applications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('job_id')->constrained('jobs_board')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('note', 500)->nullable();
            $t->string('status', 12)->default('applied');           // applied | shortlisted | hired | rejected
            $t->timestamps();
            $t->unique(['job_id', 'user_id'], 'app_once_idx');
            $t->index(['user_id', 'status'], 'app_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_profiles');
        Schema::dropIfExists('jobs_board');
    }
};
