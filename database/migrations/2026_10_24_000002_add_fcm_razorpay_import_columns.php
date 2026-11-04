<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $t) {
            $t->string('razorpay_order_id')->nullable()->unique();
            $t->string('razorpay_payment_id')->nullable()->index();
            $t->timestamp('paid_at')->nullable();
        });
        Schema::table('notifications_log', function (Blueprint $t) {
            $t->string('title', 120)->default('ABMM Sampark');
            $t->string('topic', 140)->nullable();
            $t->string('delivery_status', 12)->default('queued'); // queued, sent, failed
            $t->string('fcm_message_id')->nullable();
            $t->text('error')->nullable();
        });
        Schema::table('families', function (Blueprint $t) {
            $t->string('legacy_id', 30)->nullable()->index(); // id from the old ABMM Sampark system
        });
    }

    public function down(): void
    {
        Schema::table('donations', fn (Blueprint $t) => $t->dropColumn(['razorpay_order_id', 'razorpay_payment_id', 'paid_at']));
        Schema::table('notifications_log', fn (Blueprint $t) => $t->dropColumn(['title', 'topic', 'delivery_status', 'fcm_message_id', 'error']));
        Schema::table('families', fn (Blueprint $t) => $t->dropColumn('legacy_id'));
    }
};
