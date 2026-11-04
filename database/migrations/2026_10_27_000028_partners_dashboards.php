<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Partner dashboards: marriage bureaus, event organisers, job recruiters (subscription based) + demo-data tracking. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20)->index();                  // bureau | organiser | recruiter
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('family_id')->nullable();
            $t->string('name', 150);
            $t->string('contact_person', 100)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('country', 100)->nullable()->index();
            $t->string('pradesh', 100)->nullable()->index();   // state
            $t->string('district', 100)->nullable();
            $t->string('city', 150)->nullable();
            $t->string('zone', 100)->nullable();
            $t->text('description')->nullable();
            $t->string('registration_no', 80)->nullable();
            $t->string('status', 20)->default('pending')->index();   // pending | active | blocked
            $t->boolean('verified')->default(false);
            $t->unsignedInteger('views')->default(0);
            $t->timestamps();
        });

        Schema::create('partner_enquiries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('partner_id')->index();
            $t->unsignedBigInteger('from_user_id')->nullable()->index();
            $t->string('subject_type', 20)->nullable();        // profile | job | event
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('name', 120)->nullable();
            $t->string('phone', 30)->nullable();
            $t->text('message')->nullable();
            $t->string('status', 20)->default('new');          // new | contacted | closed
            $t->timestamps();
        });

        foreach (['matrimony_profiles', 'jobs_board', 'events'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) { $t->unsignedBigInteger('partner_id')->nullable()->index(); });
        }
        Schema::table('events', function (Blueprint $t) { $t->string('state', 100)->nullable(); $t->string('city', 150)->nullable(); $t->string('country', 100)->nullable(); $t->date('listing_until')->nullable(); });
        Schema::table('plans', function (Blueprint $t) { $t->unsignedInteger('max_items')->nullable(); });

        // starter subscription plans (prices are tax-inclusive and editable from Admin > Plans)
        $plans = [
            ['partner-bureau-quarterly', 'Marriage Bureau - Quarterly', 'bureau', 'quarterly', 2999, 50, ['Upto 50 profiles listed', 'Leads & interest inbox', 'Verified bureau badge']],
            ['partner-bureau-yearly', 'Marriage Bureau - Yearly', 'bureau', 'yearly', 9999, 500, ['Upto 500 profiles listed', 'Leads & interest inbox', 'Verified bureau badge', 'Priority in bureau directory']],
            ['partner-organiser-monthly', 'Event Organiser - Monthly', 'organiser', 'monthly', 999, 3, ['3 events per month', 'RSVP + ticketing', 'Attendee list & check-in']],
            ['partner-organiser-yearly', 'Event Organiser - Yearly', 'organiser', 'yearly', 7999, 50, ['50 events per year', 'RSVP + ticketing', 'Attendee list & check-in', 'Online event links']],
            ['partner-recruiter-monthly', 'Job Recruiter - Monthly', 'recruiter', 'monthly', 799, 5, ['5 job posts', 'Applicant inbox with contact details', 'Shortlist / hire workflow']],
            ['partner-recruiter-yearly', 'Job Recruiter - Yearly', 'recruiter', 'yearly', 5999, 100, ['100 job posts', 'Applicant inbox with contact details', 'Shortlist / hire workflow']],
        ];
        foreach ($plans as $i => [$code, $name, $aud, $period, $price, $max, $feat]) {
            if (! \Illuminate\Support\Facades\DB::table('plans')->where('code', $code)->exists()) {
                \Illuminate\Support\Facades\DB::table('plans')->insert(['code' => $code, 'name' => $name, 'audience' => $aud, 'period' => $period, 'currency' => 'INR', 'price' => $price, 'max_items' => $max,
                    'features' => json_encode($feat), 'sort' => $i, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // records created by abmm:demo-data, so they can be purged in one go
        Schema::create('demo_records', function (Blueprint $t) {
            $t->id();
            $t->string('tbl', 80);
            $t->unsignedBigInteger('rec_id');
            $t->index(['tbl', 'rec_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_records');
        Schema::dropIfExists('partner_enquiries');
        Schema::dropIfExists('partners');
    }
};
