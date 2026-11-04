<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Milestone D: online / offline Parichay events, biodata upload + digest, duplicate & fraud tools, consent based match chat. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $t) {
            $t->boolean('parichay')->default(false)->index();            // Parichay sammelan (online or offline)
            $t->boolean('profile_required')->default(false);             // only members with a paid Parinay profile may register
            $t->string('online_meeting_id', 60)->nullable();
        });
        Schema::create('event_joins', function (Blueprint $t) {          // online attendance: every "Join online" click
            $t->id();
            $t->unsignedBigInteger('event_id');
            $t->unsignedBigInteger('user_id');
            $t->timestamp('joined_at')->useCurrent();
            $t->index(['event_id', 'user_id']);
        });

        Schema::create('biodata_prefs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('profile_id')->unique();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('frequency', 10)->default('weekly');              // off | weekly | monthly
            $t->json('channels')->nullable();                            // ["app","email","whatsapp"]
            $t->unsignedTinyInteger('count')->default(5);
            $t->json('sent_ids')->nullable();                           // profiles already delivered (no repeats)
            $t->timestamp('last_sent_at')->nullable();
            $t->timestamps();
        });
        Schema::create('biodata_uploads', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('family_id')->nullable();
            $t->string('path')->nullable();
            $t->string('mime', 80)->nullable();
            $t->longText('text')->nullable();
            $t->json('parsed')->nullable();
            $t->string('status', 12)->default('parsed');                 // parsed | manual (no text could be read) | failed
            $t->string('method', 20)->nullable();                        // text | pdftotext | tesseract | claude
            $t->timestamps();
        });

        Schema::create('profile_reports', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('profile_id')->index();
            $t->unsignedBigInteger('reporter_id')->index();
            $t->string('reason', 40);                                    // fake | married | scam | abusive | duplicate | other
            $t->string('detail', 500)->nullable();
            $t->string('status', 12)->default('open')->index();          // open | actioned | dismissed
            $t->unsignedBigInteger('handled_by')->nullable();
            $t->timestamps();
        });
        Schema::create('profile_flags', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('profile_id')->index();
            $t->string('type', 30);                                      // duplicate | shared_mobile | blocklist | underage | reports | rapid_interests
            $t->string('detail', 300)->nullable();
            $t->string('status', 12)->default('open')->index();          // open | cleared | removed
            $t->unsignedBigInteger('handled_by')->nullable();
            $t->timestamps();
        });
        Schema::create('fraud_blocklist', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 10);                                      // mobile | email | name
            $t->string('value', 150);
            $t->string('reason', 200)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['kind', 'value']);
        });
        Schema::table('matrimony_profiles', function (Blueprint $t) {
            $t->timestamp('removed_at')->nullable();
            $t->string('removal_reason', 200)->nullable();
        });

        Schema::table('matrimony_interests', function (Blueprint $t) {
            $t->boolean('contact_from')->default(false);                 // sender agrees to share family contact
            $t->boolean('contact_to')->default(false);                   // receiver agrees to share family contact
        });
        Schema::create('match_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('interest_id')->index();
            $t->unsignedBigInteger('from_profile_id');
            $t->string('body', 1000);
            $t->boolean('hidden')->default(false);
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['match_messages', 'fraud_blocklist', 'profile_flags', 'profile_reports', 'biodata_uploads', 'biodata_prefs', 'event_joins'] as $t) Schema::dropIfExists($t);
    }
};
