<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('mobile', 15)->nullable()->after('email');
            $t->string('role', 20)->default('operator')->after('mobile'); // super_admin, admin, operator
            $t->string('pradesh')->nullable();
            $t->string('district')->nullable();
            $t->boolean('is_active')->default(true);
        });

        Schema::create('lookups', function (Blueprint $t) {
            $t->id();
            $t->string('type', 40)->index(); // blood_group, education, occupation, profession, business_type, surname, tehsil, pradesh, district, state
            $t->string('name');
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
            $t->unique(['type', 'name']);
        });

        Schema::create('families', function (Blueprint $t) {
            $t->id();
            $t->string('family_code', 12)->unique();
            $t->string('surname')->index();
            $t->string('head_name');
            $t->string('father_husband_name');
            $t->string('mother_name')->nullable();
            $t->string('mobile', 15)->index();
            $t->string('gender', 10);
            $t->unsignedSmallInteger('member_count')->default(1);
            $t->string('resident_type', 20)->default('OWNED');
            $t->text('address');
            $t->string('city');
            $t->string('country')->default('INDIA');
            $t->string('state')->nullable()->index();
            $t->string('district')->nullable()->index();
            $t->string('tehsil')->nullable();
            $t->string('pradesh')->nullable()->index();
            $t->string('pincode', 10)->nullable();
            $t->text('aadhar_enc')->nullable();       // encrypted via model cast
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->date('dob')->nullable();
            $t->string('marital_status', 20)->nullable();
            $t->date('anniversary')->nullable();
            $t->string('blood_group', 5)->nullable();
            $t->string('education')->nullable();
            $t->string('education_detail')->nullable();
            $t->string('occupation')->nullable();
            $t->string('occupation_detail')->nullable();
            $t->string('business_type')->nullable();
            $t->string('profession')->nullable();
            $t->string('photo')->nullable();
            $t->string('income_range')->nullable();
            $t->boolean('has_mediclaim')->default(false);
            $t->boolean('pakshik_member')->default(false);
            $t->string('status', 15)->default('active')->index(); // active, inactive, moved
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('family_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('relation', 40);
            $t->string('gender', 10)->nullable();
            $t->date('dob')->nullable();
            $t->string('blood_group', 5)->nullable();
            $t->string('education')->nullable();
            $t->string('occupation')->nullable();
            $t->string('marital_status', 20)->nullable();
            $t->string('mobile', 15)->nullable();
            $t->timestamps();
        });

        Schema::create('committee_members', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('designation')->index();
            $t->string('level', 15)->index(); // country, pradesh, state, district, tehsil
            $t->string('mobile', 15)->nullable();
            $t->date('dob')->nullable();
            $t->text('address')->nullable();
            $t->string('pradesh')->nullable();
            $t->string('district')->nullable();
            $t->date('term_start')->nullable();
            $t->date('term_end')->nullable();
            $t->string('photo')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('matrimony_profiles', function (Blueprint $t) {
            $t->id();
            $t->string('parinay_id', 12)->unique();
            $t->foreignId('family_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('gender', 10)->index();
            $t->date('dob');
            $t->string('qualification')->nullable();
            $t->string('occupation_type')->nullable();
            $t->string('occupation')->nullable();
            $t->string('height')->nullable();
            $t->boolean('manglik')->default(false);
            $t->string('mobile', 15);
            $t->text('address')->nullable();
            $t->string('state')->nullable()->index();
            $t->string('district')->nullable();
            $t->string('pincode', 10)->nullable();
            $t->string('photo')->nullable();
            $t->text('about')->nullable();
            $t->string('status', 15)->default('registered')->index(); // registered, paid, inactive, archived
            $t->string('payment_ref')->nullable();
            $t->timestamp('registered_at')->nullable();
            $t->timestamps();
        });

        Schema::create('businesses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('family_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('type')->index();
            $t->text('address');
            $t->string('email')->nullable();
            $t->string('mobile', 15);
            $t->string('image')->nullable();
            $t->text('description')->nullable();
            $t->string('status', 12)->default('pending')->index(); // pending, active, blocked
            $t->timestamps();
        });

        Schema::create('business_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('reviewer_name');
            $t->unsignedTinyInteger('rating');
            $t->text('comment')->nullable();
            $t->string('status', 12)->default('pending');
            $t->timestamps();
        });

        Schema::create('business_ads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('banner')->nullable();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->decimal('amount', 10, 2)->default(0);
            $t->string('payment_ref')->nullable();
            $t->string('status', 12)->default('pending');
            $t->timestamps();
        });

        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('venue');
            $t->date('event_date');
            $t->time('event_time')->nullable();
            $t->string('kind', 12)->default('event'); // event, meeting
            $t->string('audience', 15)->default('country');
            $t->string('audience_value')->nullable();
            $t->boolean('is_public')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('notifications_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('audience', 15);            // country, pradesh, state, district, tehsil
            $t->string('audience_value')->nullable();
            $t->string('channel', 10)->default('sms'); // sms, app, both
            $t->text('message');
            $t->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('member_count')->default(0);
            $t->unsignedInteger('seen_count')->default(0);
            $t->timestamps();
        });

        Schema::create('donations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('family_code', 12)->nullable();
            $t->decimal('amount', 10, 2);
            $t->string('email');
            $t->string('mobile', 15);
            $t->string('city')->nullable();
            $t->string('payment_ref')->nullable();
            $t->string('status', 12)->default('pending'); // pending, success, failed
            $t->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('mobile', 15)->nullable();
            $t->string('email')->nullable();
            $t->text('message');
            $t->boolean('is_read')->default(false);
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
        });

        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 30);
            $t->string('detail')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs','settings','contact_messages','donations','notifications_log','events','business_ads','business_reviews','businesses','matrimony_profiles','committee_members','members','families','lookups'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['mobile','role','pradesh','district','is_active']));
    }
};
