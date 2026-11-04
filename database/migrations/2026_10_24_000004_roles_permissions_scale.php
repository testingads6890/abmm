<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedBigInteger('created_by')->nullable()->index()->after('role');
            $t->json('permissions')->nullable()->after('created_by');
            $t->timestamp('last_login_at')->nullable();
            $t->index(['role', 'pradesh', 'district']);
        });

        // Legacy roles -> new hierarchy. Non-super users WITHOUT an area see nothing (fail-closed), so assign an area after upgrade.
        DB::table('users')->where('role', 'admin')->update(['role' => 'state_admin']);
        DB::table('users')->where('role', 'operator')->update(['role' => 'district_operator']);

        // Mobile app tokens (hashed, expiring) - no extra package needed.
        Schema::create('api_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->string('device_name', 100)->nullable();
            $t->string('fcm_token', 255)->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
        });

        // Area columns so State/District users only see their own records (set automatically from the creator's area).
        Schema::table('matrimony_profiles', fn (Blueprint $t) => $t->string('pradesh')->nullable()->index());
        Schema::table('businesses', function (Blueprint $t) {
            $t->string('pradesh')->nullable()->index();
            $t->string('district')->nullable()->index();
        });

        // Indexes for ~10 lakh families: area filters + sorting + search prefixes.
        Schema::table('families', function (Blueprint $t) {
            $t->index(['pradesh', 'district', 'tehsil'], 'fam_area_idx');
            $t->index(['district', 'id'], 'fam_district_id_idx');
            $t->index('head_name', 'fam_head_idx');
        });
        Schema::table('members', function (Blueprint $t) {
            $t->index(['family_id', 'status'], 'mem_family_status_idx');
            $t->index('mobile', 'mem_mobile_idx');
        });
        Schema::table('committee_members', fn (Blueprint $t) => $t->index(['pradesh', 'district'], 'com_area_idx'));
        Schema::table('matrimony_profiles', fn (Blueprint $t) => $t->index(['state', 'district'], 'mat_area_idx'));
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
        Schema::table('matrimony_profiles', fn (Blueprint $t) => $t->dropColumn('pradesh'));
        Schema::table('businesses', fn (Blueprint $t) => $t->dropColumn(['pradesh', 'district']));
        Schema::table('families', function (Blueprint $t) {
            $t->dropIndex('fam_area_idx'); $t->dropIndex('fam_district_id_idx'); $t->dropIndex('fam_head_idx');
        });
        Schema::table('members', function (Blueprint $t) { $t->dropIndex('mem_family_status_idx'); $t->dropIndex('mem_mobile_idx'); });
        Schema::table('committee_members', fn (Blueprint $t) => $t->dropIndex('com_area_idx'));
        Schema::table('matrimony_profiles', fn (Blueprint $t) => $t->dropIndex('mat_area_idx'));
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['role', 'pradesh', 'district']);
            $t->dropColumn(['created_by', 'permissions', 'last_login_at']);
        });
    }
};
