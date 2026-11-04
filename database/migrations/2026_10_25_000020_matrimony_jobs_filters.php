<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Matrimony 2.0 (marital status, gotra, height, income, diet, city, partner preferences, interests, shortlist)
 * + Job Portal filters (category, work mode, salary range, education, experience, gender preference; seeker preferences).
 * Also seeds the Master Data lists these screens use (Super Admin can edit all of them).
 */
return new class extends Migration {
    private const LISTS = [
        'parinay_marital' => ['Never Married', 'Divorced', 'Widowed', 'Awaiting Divorce'],
        'diet' => ['Vegetarian', 'Jain', 'Eggetarian', 'Non-Vegetarian'],
        // Yearly income bands for Matrimony. Format: "2-5 Lakh", "Below 2 Lakh", "50 Lakh and above" (min / max are read from the name).
        'salary_range' => ['Below 2 Lakh', '2-5 Lakh', '5-10 Lakh', '10-20 Lakh', '20-50 Lakh', '50 Lakh and above'],
        // Monthly salary bands for the Job Portal. Format: "10k-20k", "Below 10k", "1 Lakh and above".
        'job_salary_range' => ['Below 10k', '10k-20k', '20k-35k', '35k-60k', '60k-1 Lakh', '1 Lakh and above'],
        'job_category' => ['IT & Software', 'Accounts & Finance', 'Banking & Insurance', 'Sales & Marketing', 'Teaching & Education', 'Medical & Healthcare', 'Manufacturing & Industry',
            'Retail & Shop', 'Hospitality & Travel', 'Construction & Real Estate', 'Transport & Logistics', 'Government', 'Office Admin & Data Entry', 'Design & Media', 'Other'],
    ];

    public function up(): void
    {
        Schema::table('matrimony_profiles', function (Blueprint $t) {
            $t->string('marital_status', 20)->nullable()->index('mat_marital_idx');
            $t->string('gotra', 80)->nullable()->index('mat_gotra_idx');
            $t->unsignedSmallInteger('height_cm')->nullable();
            $t->unsignedBigInteger('annual_income')->nullable();
            $t->string('diet', 20)->nullable();
            $t->string('city', 100)->nullable();
            $t->string('country', 60)->default('INDIA');
            $t->json('partner_pref')->nullable();
            $t->boolean('visible')->default(true);
            $t->index(['gender', 'status', 'visible'], 'mat_search_idx');
            $t->index('dob', 'mat_dob_idx');
        });

        Schema::create('matrimony_interests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('from_profile_id')->constrained('matrimony_profiles')->cascadeOnDelete();
            $t->foreignId('to_profile_id')->constrained('matrimony_profiles')->cascadeOnDelete();
            $t->string('message', 300)->nullable();
            $t->string('status', 10)->default('sent');          // sent | accepted | declined
            $t->timestamps();
            $t->unique(['from_profile_id', 'to_profile_id'], 'mat_interest_once');
            $t->index(['to_profile_id', 'status'], 'mat_interest_to_idx');
        });

        Schema::create('matrimony_shortlists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('profile_id')->constrained('matrimony_profiles')->cascadeOnDelete();
            $t->foreignId('target_id')->constrained('matrimony_profiles')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['profile_id', 'target_id'], 'mat_short_once');
        });

        Schema::table('jobs_board', function (Blueprint $t) {
            $t->string('category', 80)->nullable();
            $t->string('work_mode', 10)->default('onsite');               // onsite | remote | hybrid
            $t->unsignedInteger('salary_min')->nullable();                // per month, Rs
            $t->unsignedInteger('salary_max')->nullable();
            $t->string('education', 120)->nullable();
            $t->unsignedTinyInteger('exp_min')->default(0);
            $t->unsignedTinyInteger('exp_max')->nullable();
            $t->string('gender_pref', 8)->default('any');                 // any | male | female
            $t->index(['status', 'category'], 'jobs_cat_idx');
            $t->index('salary_min', 'jobs_salary_idx');
        });

        Schema::table('job_profiles', function (Blueprint $t) {
            $t->string('gender', 10)->nullable();
            $t->string('category', 80)->nullable();                       // preferred field of work
            $t->string('work_mode', 10)->nullable();
            $t->string('preferred_cities', 200)->nullable();
            $t->unsignedInteger('expected_salary_num')->nullable();       // per month, Rs
            $t->string('notice_period', 20)->nullable();                  // Immediate | 15 days | 30 days | 60 days | 90 days
            $t->index('category', 'jp_cat_idx');
        });

        $now = now();
        foreach (self::LISTS as $type => $names) {
            foreach ($names as $i => $n) {
                if (! DB::table('lookups')->where('type', $type)->where('name', $n)->exists()) {   // NULL parent_id defeats the unique index, so check first
                    DB::table('lookups')->insert(['type' => $type, 'name' => $n, 'parent_id' => null, 'sort' => $i, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
        cache()->forget('api.masters');
        cache()->forget('lookups.grouped');
    }

    public function down(): void
    {
        Schema::table('job_profiles', function (Blueprint $t) {
            $t->dropIndex('jp_cat_idx');
            $t->dropColumn(['gender', 'category', 'work_mode', 'preferred_cities', 'expected_salary_num', 'notice_period']);
        });
        Schema::table('jobs_board', function (Blueprint $t) {
            $t->dropIndex('jobs_cat_idx');
            $t->dropIndex('jobs_salary_idx');
            $t->dropColumn(['category', 'work_mode', 'salary_min', 'salary_max', 'education', 'exp_min', 'exp_max', 'gender_pref']);
        });
        Schema::dropIfExists('matrimony_shortlists');
        Schema::dropIfExists('matrimony_interests');
        Schema::table('matrimony_profiles', function (Blueprint $t) {
            $t->dropIndex('mat_search_idx');
            $t->dropIndex('mat_dob_idx');
            $t->dropIndex('mat_marital_idx');
            $t->dropIndex('mat_gotra_idx');
            $t->dropColumn(['marital_status', 'gotra', 'height_cm', 'annual_income', 'diet', 'city', 'country', 'partner_pref', 'visible']);
        });
        DB::table('lookups')->whereIn('type', array_keys(self::LISTS))->delete();
    }
};
