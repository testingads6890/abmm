<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name', 160)->unique();
            $t->string('email', 160);
            $t->decimal('fee', 10, 2)->default(0); // 0 = no payment, submit directly
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('trusts', function (Blueprint $t) {
            $t->id();
            // common fields (same for every category)
            $t->string('name', 200);                       // applicant name
            $t->string('email', 160);
            $t->date('dob')->nullable();
            $t->string('contact', 20);                     // mobile number
            $t->string('gender', 20)->nullable();
            $t->string('marital_status', 20)->nullable();  // MARRIED / UNMARRIED / DIVORCEE / WIDOW / WIDOWER (master)
            $t->string('city', 100)->nullable();
            $t->text('aadhar_enc')->nullable();            // encrypted
            $t->string('trust_category', 120)->index();    // dropdown (master)
            $t->string('trust_name', 200)->index();        // dropdown (master, depends on category)
            // optional address block
            $t->text('address')->nullable();
            $t->string('country', 60)->default('INDIA');
            $t->string('state', 80)->nullable()->index();
            $t->string('district', 80)->nullable()->index();
            $t->string('pincode', 6)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('trust_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('trust_id')->constrained()->cascadeOnDelete();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->decimal('amount', 10, 2)->default(0);
            $t->string('status', 12)->default('pending')->index(); // pending (awaiting payment), submitted, failed
            $t->string('razorpay_order_id')->nullable()->index();
            $t->string('razorpay_payment_id')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->text('mail_error')->nullable();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_submissions');
        Schema::dropIfExists('trusts');
        Schema::dropIfExists('departments');
    }
};
