<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lookups', function (Blueprint $t) {
            $t->unsignedBigInteger('parent_id')->nullable()->after('type')->index();   // state -> pradesh, district -> state, tehsil -> district
            $t->string('pincode', 10)->nullable()->after('name');                       // auto-fills on the family form
            $t->boolean('is_active')->default(true)->after('sort');
            $t->index(['type', 'is_active', 'sort']);
            // same tehsil/district name may exist under different parents
            $t->dropUnique(['type', 'name']);
            $t->unique(['type', 'name', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('lookups', function (Blueprint $t) {
            $t->dropUnique(['type', 'name', 'parent_id']);
            $t->unique(['type', 'name']);
            $t->dropIndex(['type', 'is_active', 'sort']);
            $t->dropColumn(['parent_id', 'pincode', 'is_active']);
        });
    }
};
