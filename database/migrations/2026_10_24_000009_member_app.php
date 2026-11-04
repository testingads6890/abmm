<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Member-app login accounts live in `users` (role = member) so the same hashed token table works.
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->unsignedBigInteger('member_id')->nullable()->index();
            $t->index('mobile', 'users_mobile_idx');
        });
        Schema::table('families', function (Blueprint $t) {
            $t->string('source', 10)->default('admin')->index();   // admin | app
            $t->timestamp('app_registered_at')->nullable();
        });
        Schema::table('members', function (Blueprint $t) {
            $t->string('source', 10)->default('admin');
            $t->string('approval', 10)->default('approved')->index(); // approved | pending | rejected (member joining an existing family from the app)
            $t->index(['mobile', 'status'], 'mem_mobile_status_idx');
        });
        Schema::table('trusts', function (Blueprint $t) {
            $t->string('source', 10)->default('admin')->index();
            $t->unsignedBigInteger('family_id')->nullable()->index();
            $t->unsignedBigInteger('member_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
        });
        Schema::create('downloads', function (Blueprint $t) {
            $t->id();
            $t->string('title', 250);
            $t->string('url', 500);
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downloads');
        Schema::table('trusts', fn (Blueprint $t) => $t->dropColumn(['source', 'family_id', 'member_id', 'user_id']));
        Schema::table('members', function (Blueprint $t) { $t->dropIndex('mem_mobile_status_idx'); $t->dropColumn(['source', 'approval']); });
        Schema::table('families', fn (Blueprint $t) => $t->dropColumn(['source', 'app_registered_at']));
        Schema::table('users', function (Blueprint $t) { $t->dropIndex('users_mobile_idx'); $t->dropColumn(['family_id', 'member_id']); });
    }
};
