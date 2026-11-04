<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $t) {
            $t->string('member_code', 12)->nullable()->unique();
            $t->boolean('is_head')->default(false)->index();
            $t->string('surname')->nullable()->index();
            $t->string('email')->nullable();
            $t->string('profession')->nullable();
            $t->string('business_type')->nullable();
            $t->string('status', 10)->default('active')->index();       // active | archived  (archived == "Hanging Data")
            $t->string('archive_type', 20)->nullable()->index();         // Died, Married, Migrated, Naya Chulha, Wrong Entry
            $t->timestamp('archived_at')->nullable();
            $t->string('archive_note')->nullable();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->text('address')->nullable();
            $t->string('country')->nullable()->default('INDIA');
            $t->string('tehsil')->nullable();
            $t->string('pincode', 10)->nullable();
            $t->string('locale', 5)->default('en');
            $t->boolean('desktop_notify')->default(false);
        });

        Schema::table('matrimony_profiles', function (Blueprint $t) {
            $t->string('degree')->nullable();
            $t->string('surname')->nullable()->index();
            $t->string('member_code', 12)->nullable();
            $t->timestamp('archived_at')->nullable();
        });

        Schema::table('businesses', function (Blueprint $t) {
            $t->string('contact_person')->nullable();
            $t->string('member_code', 12)->nullable()->index();
        });

        Schema::table('business_reviews', function (Blueprint $t) {
            $t->string('reviewer_mobile', 15)->nullable();
        });

        Schema::table('business_ads', function (Blueprint $t) {
            $t->string('location')->nullable();
            $t->string('contact_person')->nullable();
            $t->string('contact_mobile', 15)->nullable();
            $t->string('member_code', 12)->nullable()->index();
        });

        Schema::table('committee_members', fn (Blueprint $t) => $t->string('tehsil')->nullable()->index());

        Schema::table('donations', fn (Blueprint $t) => $t->text('pan_enc')->nullable()); // encrypted, for 80G receipts only

        Schema::table('notifications_log', function (Blueprint $t) {
            $t->string('kind', 10)->default('notice'); // notice | meeting
        });

        Schema::create('notification_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('notification_id')->constrained('notifications_log')->cascadeOnDelete();
            $t->foreignId('family_id')->constrained()->cascadeOnDelete();
            $t->timestamp('seen_at')->nullable();
            $t->unique(['notification_id', 'family_id']);
        });

        Schema::create('user_notifications', function (Blueprint $t) { // header bell
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('url')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        Schema::create('feedbacks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('message');
            $t->timestamps();
        });

        Schema::create('offices', function (Blueprint $t) {
            $t->id();
            $t->string('title');            // MAHASABHA OFFICE, SABHAPATI OFFICE, ...
            $t->string('person')->nullable();
            $t->text('address')->nullable();
            $t->string('phones')->nullable();
            $t->string('email')->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['offices', 'feedbacks', 'user_notifications', 'notification_recipients'] as $t) Schema::dropIfExists($t);
    }
};
