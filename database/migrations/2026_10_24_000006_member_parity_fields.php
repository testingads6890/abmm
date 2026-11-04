<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('must_change_password')->default(false));

        Schema::table('committee_members', fn (Blueprint $t) => $t->string('surname')->nullable());

        Schema::table('members', function (Blueprint $t) {
            $t->string('father_husband_name')->nullable();
            $t->date('anniversary')->nullable();
            $t->text('aadhar_enc')->nullable();
            $t->string('photo')->nullable();
            $t->string('education_detail')->nullable();
            $t->string('occupation_detail')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('must_change_password'));
        Schema::table('committee_members', fn (Blueprint $t) => $t->dropColumn('surname'));
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn(['father_husband_name', 'anniversary', 'aadhar_enc', 'photo', 'education_detail', 'occupation_detail']));
    }
};
