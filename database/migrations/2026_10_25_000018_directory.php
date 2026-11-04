<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Community directory privacy: everyone is listed by default, phone is shown only if the member opts in; anyone can hide completely. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $t) {
            $t->boolean('dir_hidden')->default(false);
            $t->boolean('dir_show_phone')->default(false);
            $t->index('name', 'mem_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $t) {
            $t->dropIndex('mem_name_idx');
            $t->dropColumn(['dir_hidden', 'dir_show_phone']);
        });
    }
};
