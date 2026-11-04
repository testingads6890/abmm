<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Milestone A / 1: Country > State > District > City > Zone, per-country settings, new area columns. Additive only. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $t) {
            $t->id();
            $t->string('country', 100)->default('INDIA');
            $t->string('state', 150)->nullable();
            $t->string('district', 150)->nullable();
            $t->string('city', 150);
            $t->string('name', 100);                 // "Zone 1", "North Zone" ...
            $t->string('code', 20)->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['country', 'city', 'name'], 'zones_unique');
            $t->index(['country', 'city'], 'zones_city_idx');
        });

        Schema::create('country_settings', function (Blueprint $t) {
            $t->id();
            $t->string('country', 100)->unique();
            $t->string('iso', 3)->nullable();
            $t->string('dial_code', 8)->default('+91');
            $t->string('currency', 3)->default('INR');
            $t->string('gateway', 20)->default('razorpay');   // razorpay | stripe | manual
            $t->string('tax_name', 20)->nullable();            // GST / VAT
            $t->decimal('tax_rate', 5, 2)->default(0);
            $t->string('language', 5)->default('en');
            $t->string('timezone', 50)->default('Asia/Kolkata');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('city', 150)->nullable()->after('tehsil');
            $t->string('zone', 100)->nullable()->after('city');
        });
        Schema::table('families', function (Blueprint $t) {
            $t->string('zone', 100)->nullable()->after('city');
            $t->index(['country', 'city', 'zone'], 'families_geo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $t) { $t->dropIndex('families_geo_idx'); $t->dropColumn('zone'); });
        Schema::table('users', function (Blueprint $t) { $t->dropColumn(['city', 'zone']); });
        Schema::dropIfExists('country_settings');
        Schema::dropIfExists('zones');
    }
};
