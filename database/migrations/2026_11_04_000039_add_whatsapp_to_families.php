<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('families', function (Blueprint $t) {
            if (! Schema::hasColumn('families', 'whatsapp')) $t->string('whatsapp', 15)->nullable()->after('mobile');
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $t) {
            if (Schema::hasColumn('families', 'whatsapp')) $t->dropColumn('whatsapp');
        });
    }
};
