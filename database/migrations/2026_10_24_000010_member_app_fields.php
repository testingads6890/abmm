<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $t) {
            $t->string('mother_name')->nullable();
            $t->string('city')->nullable();
            $t->text('address')->nullable();
            $t->string('pincode', 10)->nullable();
            $t->string('resident_type', 20)->nullable();
            $t->string('income_range')->nullable();
            $t->string('profession_detail')->nullable();
            $t->string('business_detail')->nullable();
            $t->unsignedBigInteger('requested_by')->nullable(); // user who asked to join the family (approval flow)
        });
        Schema::table('families', function (Blueprint $t) {
            $t->string('profession_detail')->nullable();
            $t->string('business_detail')->nullable();
        });
        Schema::table('lookups', fn (Blueprint $t) => $t->json('meta')->nullable()); // e.g. trust_name: address, email, contact, state, country, pdf_url
        Schema::table('matrimony_profiles', function (Blueprint $t) {
            $t->unsignedBigInteger('member_id')->nullable()->index();
            $t->string('razorpay_order_id')->nullable()->index();
            $t->date('paid_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('matrimony_profiles', fn (Blueprint $t) => $t->dropColumn(['member_id', 'razorpay_order_id', 'paid_until']));
        Schema::table('lookups', fn (Blueprint $t) => $t->dropColumn('meta'));
        Schema::table('families', fn (Blueprint $t) => $t->dropColumn(['profession_detail', 'business_detail']));
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn(['mother_name', 'city', 'address', 'pincode', 'resident_type', 'income_range', 'profession_detail', 'business_detail', 'requested_by']));
    }
};
