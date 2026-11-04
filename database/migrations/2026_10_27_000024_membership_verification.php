<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Milestone A / 4: membership ID, category, chapter, ID-proof verification and two-member reference. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('families', function (Blueprint $t) {
            $t->string('membership_no', 24)->nullable()->unique()->after('family_code');
            $t->string('member_category', 20)->nullable()->after('membership_no');   // life | annual | patron | general
            $t->string('chapter', 150)->nullable()->after('member_category');
            $t->string('verification_status', 12)->default('unverified')->after('chapter'); // unverified | submitted | verified | rejected
            $t->string('id_proof_type', 30)->nullable();
            $t->string('id_proof_path', 255)->nullable();
            $t->string('verification_note', 255)->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->foreignId('verified_by')->nullable();
            $t->index('verification_status', 'families_verif_idx');
        });

        Schema::create('member_references', function (Blueprint $t) {
            $t->id();
            $t->foreignId('family_id')->index();           // the applicant
            $t->foreignId('referee_family_id')->nullable()->index();
            $t->string('referee_name', 150);
            $t->string('referee_mobile', 20);
            $t->string('status', 10)->default('pending');   // pending | confirmed | declined
            $t->timestamp('responded_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_references');
        Schema::table('families', function (Blueprint $t) {
            $t->dropIndex('families_verif_idx');
            $t->dropColumn(['membership_no', 'member_category', 'chapter', 'verification_status', 'id_proof_type', 'id_proof_path', 'verification_note', 'verified_at', 'verified_by']);
        });
    }
};
