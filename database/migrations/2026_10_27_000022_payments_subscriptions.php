<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Milestone A / 2: plans, coupons, subscriptions, unified payments ledger, refunds, receipts. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name', 120);
            $t->string('audience', 20);              // member | business | bureau | vendor | advertiser | institution | training
            $t->string('period', 12);                // monthly | quarterly | half_yearly | yearly | one_time
            $t->string('country', 100)->nullable();  // null = all countries
            $t->string('currency', 3)->default('INR');
            $t->decimal('price', 12, 2);
            $t->text('description')->nullable();
            $t->json('features')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['audience', 'is_active']);
        });

        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('type', 8)->default('percent'); // percent | flat
            $t->decimal('value', 10, 2);
            $t->unsignedInteger('max_uses')->nullable();
            $t->unsignedInteger('used')->default(0);
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->string('audience', 20)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->index();
            $t->foreignId('family_id')->nullable()->index();
            $t->string('subscriber_type', 20)->default('member');   // member | business | bureau | vendor ...
            $t->unsignedBigInteger('subscriber_id')->nullable();    // business id / bureau id etc.
            $t->foreignId('plan_id')->constrained('plans');
            $t->string('status', 12)->default('pending');           // pending | active | expired | cancelled
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable()->index();
            $t->boolean('auto_renew')->default(false);
            $t->timestamp('reminded_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'ends_on']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('number', 24)->unique();                      // PAY-2026-000001 (also receipt basis)
            $t->string('purpose', 20);                               // subscription | donation | form | event | ad | other
            $t->string('purpose_ref', 40)->nullable();               // e.g. subscription id / form submission id
            $t->foreignId('user_id')->nullable()->index();
            $t->foreignId('family_id')->nullable()->index();
            $t->string('country', 100)->nullable();
            $t->string('city', 150)->nullable();
            $t->string('payer_name', 150)->nullable();
            $t->string('payer_email', 150)->nullable();
            $t->string('payer_mobile', 20)->nullable();
            $t->string('currency', 3)->default('INR');
            $t->decimal('amount', 12, 2);                            // gross, tax inclusive
            $t->decimal('tax_amount', 12, 2)->default(0);
            $t->decimal('discount', 12, 2)->default(0);
            $t->string('coupon', 30)->nullable();
            $t->string('gateway', 20);                               // razorpay | stripe | manual
            $t->string('gateway_order_id', 100)->nullable()->index();
            $t->string('gateway_payment_id', 100)->nullable()->index();
            $t->string('status', 12)->default('created');            // created | paid | failed | refunded | partial_refund
            $t->decimal('refunded', 12, 2)->default(0);
            $t->timestamp('paid_at')->nullable();
            $t->boolean('reconciled')->default(false);
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['status', 'paid_at']);
            $t->index(['country', 'purpose']);
        });

        Schema::create('payment_refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->string('reason', 255)->nullable();
            $t->string('gateway_refund_id', 100)->nullable();
            $t->string('status', 12)->default('done');
            $t->foreignId('by_user')->nullable();
            $t->timestamps();
        });

        Schema::create('receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $t->string('number', 24)->unique();                      // RCT-2026-000001
            $t->string('path', 255)->nullable();                     // storage/app/private/receipts/...
            $t->timestamp('emailed_at')->nullable();
            $t->timestamp('whatsapped_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['receipts', 'payment_refunds', 'payments', 'subscriptions', 'coupons', 'plans'] as $x) Schema::dropIfExists($x);
    }
};
