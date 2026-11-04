<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['trusts', 'families', 'members', 'committee_members', 'matrimony_profiles'];

    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $t) {
            $t->id();
            $t->string('form', 30)->index();       // trust, family, member, committee, matrimony
            $t->string('key', 60);                 // machine name, never changes after creation
            $t->string('label', 120);
            $t->string('category', 120)->nullable();   // trust form only: field shows just for this Trust Category (null = all)
            $t->string('type', 12)->default('text'); // text, textarea, number, date, email, phone, select, checkbox
            $t->text('options')->nullable();         // select: one option per line
            $t->boolean('required')->default(false);
            $t->boolean('show_in_list')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
            $t->unique(['form', 'key']);
        });
        foreach ($this->tables as $tb) Schema::table($tb, fn (Blueprint $t) => $t->json('extra')->nullable());
    }

    public function down(): void
    {
        foreach ($this->tables as $tb) Schema::table($tb, fn (Blueprint $t) => $t->dropColumn('extra'));
        Schema::dropIfExists('custom_fields');
    }
};
