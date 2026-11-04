<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** iOS: Apple In-App Purchase mapping and verified transactions (digital goods bought inside the iPhone app). */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('iap_products', function (Blueprint $t) {
            $t->id();
            $t->string('product_id', 120)->unique();                     // App Store Connect product identifier
            $t->string('kind', 10);                                      // course | plan
            $t->unsignedBigInteger('ref_id');                            // courses.id or plans.id
            $t->string('title')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('iap_transactions', function (Blueprint $t) {
            $t->id();
            $t->string('transaction_id', 40)->unique();
            $t->string('original_transaction_id', 40)->index();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->string('product_id', 120);
            $t->string('kind', 10);
            $t->unsignedBigInteger('ref_id');
            $t->string('environment', 12)->default('Production');
            $t->dateTime('purchased_at')->nullable();
            $t->dateTime('expires_at')->nullable();
            $t->string('status', 10)->default('active');                 // active | expired | refunded
            $t->unsignedBigInteger('subscription_id')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('iap_transactions'); Schema::dropIfExists('iap_products'); }
};
