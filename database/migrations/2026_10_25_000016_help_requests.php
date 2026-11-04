<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Help & support requests (education, medical, scholarship ...) raised from the member app. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('help_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('family_id')->index();
            $t->unsignedBigInteger('member_id')->nullable();
            $t->string('pradesh')->nullable();
            $t->string('state')->nullable();
            $t->string('district')->nullable();
            $t->string('type', 15);                                 // education | medical | scholarship | other
            $t->string('title', 200);
            $t->text('details');
            $t->decimal('amount', 12, 2)->nullable();
            $t->json('documents')->nullable();                      // private-disk paths
            $t->string('status', 12)->default('submitted');         // submitted | in_review | approved | rejected | forwarded | closed
            $t->string('committee_note', 500)->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['pradesh', 'status'], 'help_area_idx');
            $t->index(['district', 'status'], 'help_district_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('help_requests'); }
};
