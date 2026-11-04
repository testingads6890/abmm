<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Milestone E: jobs & mentorship, blood donors, donation campaigns (80G), investments & business growth. */
return new class extends Migration {
    /** country / pradesh / district / city / zone columns used by the admin's area scoping. */
    private function area(Blueprint $t): void
    {
        $t->string('country', 100)->nullable()->index();
        $t->string('pradesh', 100)->nullable()->index();
        $t->string('district', 100)->nullable();
        $t->string('city', 150)->nullable()->index();
        $t->string('zone', 100)->nullable();
    }

    public function up(): void
    {
        /* ------------------------------------------------ Jobs: paid posts, featured jobs, alerts, drives, mentorship */
        Schema::table('jobs_board', function (Blueprint $t) {
            $t->timestamp('featured_until')->nullable()->index();
            $t->unsignedBigInteger('paid_order_id')->nullable();
        });
        Schema::table('events', function (Blueprint $t) { $t->string('series', 40)->nullable()->index(); });   // e.g. financial_literacy

        Schema::create('job_packs', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('kind', 12);                                      // post (paid listing) | featured (top of list)
            $t->decimal('price', 10, 2)->default(0);
            $t->string('currency', 3)->default('INR');
            $t->unsignedSmallInteger('days')->default(30);
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->string('description')->nullable();
            $t->timestamps();
        });
        Schema::create('job_orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('job_id')->index();
            $t->unsignedBigInteger('pack_id');
            $t->unsignedBigInteger('user_id')->index();
            $t->decimal('amount', 10, 2)->default(0);
            $t->string('status', 20)->default('awaiting_payment');       // awaiting_payment | paid | cancelled
            $t->unsignedBigInteger('payment_id')->nullable();
            $t->timestamps();
        });
        Schema::create('job_alerts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('keywords', 150)->nullable();
            $t->string('category', 80)->nullable();
            $t->string('city', 100)->nullable();
            $t->string('job_type', 12)->nullable();
            $t->string('work_mode', 10)->nullable();
            $t->unsignedInteger('min_salary')->nullable();
            $t->string('frequency', 8)->default('instant');             // instant | daily
            $t->boolean('active')->default(true);
            $t->timestamp('last_sent_at')->nullable();
            $t->timestamps();
        });

        Schema::create('placement_drives', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();                  // college / company / recruiter who posted
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->string('drive_type', 12)->default('campus');             // campus | internship | walkin
            $t->string('title');
            $t->string('institution')->nullable();
            $t->string('company')->nullable();
            $t->date('drive_date')->nullable();
            $t->date('last_date')->nullable();
            $t->unsignedSmallInteger('positions')->default(1);
            $t->string('stipend', 80)->nullable();
            $t->string('eligibility', 300)->nullable();
            $t->text('description')->nullable();
            $t->string('venue')->nullable();
            $t->string('contact_phone', 20)->nullable();
            $t->string('status', 10)->default('pending')->index();      // pending | live | rejected | closed
            $t->string('admin_note')->nullable();
            $this->area($t);
            $t->timestamps();
        });
        Schema::create('drive_registrations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('drive_id');
            $t->unsignedBigInteger('user_id');
            $t->string('college', 150)->nullable();
            $t->string('course', 100)->nullable();
            $t->string('resume_url', 300)->nullable();
            $t->string('status', 12)->default('registered');             // registered | shortlisted | selected | rejected
            $t->timestamps();
            $t->unique(['drive_id', 'user_id']);
        });

        Schema::create('mentor_profiles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->unique();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->string('headline', 160);
            $t->string('industry', 100)->nullable();
            $t->string('expertise', 300)->nullable();                    // comma separated skills / topics
            $t->unsignedTinyInteger('experience_years')->default(0);
            $t->string('languages', 120)->nullable();
            $t->string('mode', 8)->default('online');                    // online | offline | both
            $t->text('bio')->nullable();
            $t->string('linkedin', 200)->nullable();
            $t->unsignedTinyInteger('max_mentees')->default(3);
            $t->boolean('offers_counselling')->default(false);           // career counselling for students
            $t->boolean('verified')->default(false);
            $t->string('status', 10)->default('pending')->index();      // pending | approved | paused | blocked
            $this->area($t);
            $t->timestamps();
        });
        Schema::create('mentee_profiles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->unique();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->string('stage', 20)->default('student');                 // student | graduate | working | switching
            $t->string('education', 150)->nullable();
            $t->string('interests', 300)->nullable();
            $t->text('goals')->nullable();
            $t->string('mode', 8)->default('online');
            $this->area($t);
            $t->timestamps();
        });
        Schema::create('mentorships', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('mentor_id')->index();                // mentor_profiles.id
            $t->unsignedBigInteger('mentee_user_id')->index();
            $t->unsignedBigInteger('mentor_user_id')->index();
            $t->string('kind', 12)->default('mentoring');                // mentoring | counselling
            $t->string('topic', 160);
            $t->text('message')->nullable();
            $t->string('status', 12)->default('requested');              // requested | active | declined | completed | cancelled
            $t->string('mentor_note', 300)->nullable();
            $t->timestamps();
        });
        Schema::create('mentor_sessions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('mentorship_id')->index();
            $t->dateTime('scheduled_at');
            $t->unsignedSmallInteger('duration_min')->default(30);
            $t->string('mode', 8)->default('online');
            $t->string('meeting_link', 300)->nullable();
            $t->string('agenda', 300)->nullable();
            $t->text('notes')->nullable();
            $t->string('status', 12)->default('scheduled');             // scheduled | done | cancelled
            $t->unsignedTinyInteger('rating')->nullable();              // mentee's 1-5
            $t->string('feedback', 300)->nullable();
            $t->boolean('reminded')->default(false);
            $t->timestamps();
        });

        /* ------------------------------------------------ Blood donors */
        Schema::create('blood_donors', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->unique();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->string('blood_group', 4)->index();
            $t->date('last_donated_on')->nullable();
            $t->boolean('available')->default(true);
            $t->boolean('emergency_ok')->default(true);                  // accepts emergency alerts
            $t->unsignedSmallInteger('donations_count')->default(0);
            $t->string('pincode', 10)->nullable();
            $t->string('note', 200)->nullable();
            $t->string('status', 10)->default('active')->index();       // active | blocked
            $this->area($t);
            $t->timestamps();
        });
        Schema::create('blood_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->string('patient_name', 120);
            $t->string('blood_group', 4)->index();
            $t->unsignedTinyInteger('units')->default(1);
            $t->string('hospital', 200);
            $t->date('needed_by')->nullable();
            $t->string('urgency', 10)->default('normal');                // normal | emergency
            $t->string('contact_name', 100)->nullable();
            $t->string('contact_phone', 20);
            $t->string('note', 300)->nullable();
            $t->string('status', 10)->default('open')->index();         // open | fulfilled | closed
            $t->unsignedSmallInteger('notified')->default(0);
            $this->area($t);
            $t->timestamps();
        });
        Schema::create('blood_links', function (Blueprint $t) {          // consent: donor must approve before the requester sees the phone
            $t->id();
            $t->unsignedBigInteger('donor_user_id')->index();
            $t->unsignedBigInteger('requester_user_id')->index();
            $t->unsignedBigInteger('request_id')->nullable()->index();
            $t->string('message', 250)->nullable();
            $t->string('status', 10)->default('pending');                // pending | approved | declined | donated
            $t->timestamps();
        });
        Schema::create('blood_drives', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('title');
            $t->string('organiser', 150)->nullable();
            $t->string('venue', 200);
            $t->date('drive_date');
            $t->string('timing', 60)->nullable();
            $t->text('description')->nullable();
            $t->string('contact_phone', 20)->nullable();
            $t->unsignedInteger('target_units')->default(0);
            $t->unsignedInteger('collected_units')->default(0);
            $t->string('status', 10)->default('pending')->index();     // pending | live | done | cancelled
            $this->area($t);
            $t->timestamps();
        });
        Schema::create('blood_drive_regs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('drive_id');
            $t->unsignedBigInteger('user_id');
            $t->boolean('attended')->default(false);
            $t->boolean('donated')->default(false);
            $t->timestamps();
            $t->unique(['drive_id', 'user_id']);
        });

        /* ------------------------------------------------ Donation campaigns */
        Schema::create('donation_entities', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('kind', 12)->default('trust');                    // trust | ngo | mahasabha | unit
            $t->string('pan', 10)->nullable();
            $t->string('reg_no', 60)->nullable();
            $t->boolean('has_80g')->default(false);
            $t->string('g80_number', 80)->nullable();
            $t->date('g80_valid_from')->nullable();
            $t->date('g80_valid_to')->nullable();
            $t->string('address', 300)->nullable();
            $t->string('signatory', 120)->nullable();
            $t->string('country', 100)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('entity_id')->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('assistance_id')->nullable();
            $t->string('title');
            $t->string('category', 12)->default('other');                // education | medical | relief | temple | other
            $t->text('story')->nullable();
            $t->string('beneficiary', 150)->nullable();
            $t->string('banner', 300)->nullable();
            $t->decimal('target_amount', 12, 2)->default(0);
            $t->decimal('raised_amount', 12, 2)->default(0);
            $t->string('currency', 3)->default('INR');
            $t->unsignedInteger('donors_count')->default(0);
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->boolean('featured')->default(false);
            $t->string('status', 10)->default('draft')->index();        // draft | live | completed | closed
            $this->area($t);
            $t->timestamps();
        });
        Schema::create('campaign_donations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('campaign_id')->index();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->string('donor_name', 120);
            $t->string('donor_mobile', 20)->nullable();
            $t->string('donor_email', 150)->nullable();
            $t->text('pan_enc')->nullable();                             // encrypted, only for 80G
            $t->string('address', 300)->nullable();
            $t->decimal('amount', 12, 2);
            $t->string('currency', 3)->default('INR');
            $t->boolean('anonymous')->default(false);
            $t->unsignedBigInteger('payment_id')->nullable()->index();
            $t->string('status', 12)->default('pending')->index();      // pending | paid | refunded
            $t->string('g80_no', 40)->nullable()->unique();
            $t->string('g80_path', 200)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
        Schema::create('utilisation_reports', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('campaign_id')->index();
            $t->string('title');
            $t->decimal('amount_spent', 12, 2)->default(0);
            $t->text('details')->nullable();
            $t->string('document_url', 300)->nullable();
            $t->date('report_date')->nullable();
            $t->boolean('published')->default(true);
            $t->timestamps();
        });
        Schema::create('assistance_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->string('kind', 12)->default('relief');                   // medical | education | relief | other
            $t->string('title');
            $t->string('beneficiary', 150)->nullable();
            $t->text('details');
            $t->decimal('amount_needed', 12, 2)->default(0);
            $t->json('documents')->nullable();                           // links to hospital bills / fee slips
            $t->string('status', 12)->default('pending')->index();     // pending | verified | approved | rejected | disbursed
            $t->decimal('amount_approved', 12, 2)->nullable();
            $t->string('admin_note', 300)->nullable();
            $t->unsignedBigInteger('campaign_id')->nullable();
            $this->area($t);
            $t->timestamps();
        });
        Schema::create('volunteers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->unsignedBigInteger('campaign_id')->nullable()->index();
            $t->string('skills', 250)->nullable();
            $t->string('availability', 120)->nullable();
            $t->unsignedSmallInteger('hours_logged')->default(0);
            $t->string('note', 250)->nullable();
            $t->string('status', 10)->default('pending');               // pending | active | inactive
            $this->area($t);
            $t->timestamps();
        });

        /* ------------------------------------------------ Investments & business growth */
        Schema::create('investment_opportunities', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('kind', 14)->default('startup');                  // startup | real_estate | bond | fund | franchise | partnership
            $t->string('sector', 80)->nullable();
            $t->string('stage', 40)->nullable();
            $t->decimal('ticket_min', 14, 2)->nullable();
            $t->decimal('ticket_max', 14, 2)->nullable();
            $t->string('currency', 3)->default('INR');
            $t->string('expected_return', 80)->nullable();
            $t->string('tenure', 60)->nullable();
            $t->text('summary')->nullable();
            $t->text('details')->nullable();
            $t->text('risk_note')->nullable();
            $t->string('pitch_deck_url', 300)->nullable();
            $t->boolean('featured')->default(false);
            $t->unsignedInteger('interests')->default(0);
            $t->string('status', 10)->default('draft')->index();        // draft | live | closed
            $this->area($t);
            $t->timestamps();
        });
        Schema::create('investor_profiles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->unique();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->string('role', 12)->default('investor');                 // investor | entrepreneur | both
            $t->string('company', 150)->nullable();
            $t->string('sectors', 250)->nullable();
            $t->string('stage_pref', 80)->nullable();
            $t->decimal('ticket_min', 14, 2)->nullable();
            $t->decimal('ticket_max', 14, 2)->nullable();
            $t->text('bio')->nullable();
            $t->string('website', 200)->nullable();
            $t->string('linkedin', 200)->nullable();
            $t->boolean('visible')->default(true);
            $t->boolean('verified')->default(false);
            $t->string('status', 10)->default('pending')->index();      // pending | approved | blocked
            $this->area($t);
            $t->timestamps();
        });
        Schema::create('pitches', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->string('title');
            $t->string('company', 150)->nullable();
            $t->string('sector', 80)->nullable();
            $t->string('stage', 40)->nullable();
            $t->decimal('ask_amount', 14, 2)->nullable();
            $t->decimal('equity_pct', 5, 2)->nullable();
            $t->string('currency', 3)->default('INR');
            $t->text('summary');
            $t->string('deck_url', 300)->nullable();
            $t->string('website', 200)->nullable();
            $t->boolean('showcase')->default(false);                     // featured in the business showcase
            $t->unsignedInteger('interests')->default(0);
            $t->string('status', 10)->default('pending')->index();      // pending | approved | rejected | closed
            $t->string('admin_note', 300)->nullable();
            $this->area($t);
            $t->timestamps();
        });
        Schema::create('investment_interests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('opportunity_id')->nullable()->index();
            $t->unsignedBigInteger('pitch_id')->nullable()->index();
            $t->decimal('amount', 14, 2)->nullable();
            $t->string('message', 400)->nullable();
            $t->string('status', 10)->default('new');                    // new | contacted | closed
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['investment_interests', 'pitches', 'investor_profiles', 'investment_opportunities', 'volunteers', 'assistance_requests', 'utilisation_reports', 'campaign_donations', 'campaigns', 'donation_entities',
            'blood_drive_regs', 'blood_drives', 'blood_links', 'blood_requests', 'blood_donors', 'mentor_sessions', 'mentorships', 'mentee_profiles', 'mentor_profiles', 'drive_registrations', 'placement_drives',
            'job_alerts', 'job_orders', 'job_packs'] as $tb) Schema::dropIfExists($tb);
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('series'));
        Schema::table('jobs_board', fn (Blueprint $t) => $t->dropColumn(['featured_until', 'paid_order_id']));
    }
};
