<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('families', fn (Blueprint $t) => $t->unsignedBigInteger('referred_by')->nullable()->index()); // family id of the referrer
    }

    public function down(): void
    {
        Schema::table('families', fn (Blueprint $t) => $t->dropColumn('referred_by'));
    }
};
