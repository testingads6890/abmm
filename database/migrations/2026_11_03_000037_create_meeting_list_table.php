<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Meeting list: Kariyakarni Sabha / Kariyakarni Mandal Sabha meetings, entered by Super Admin and State Sub-Admins. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('meeting_list', function (Blueprint $t) {
            $t->id();
            $t->string('committee', 40);            // kariyakarni_sabha | kariyakarni_mandal_sabha | both
            $t->string('meeting_type', 20);         // regular | special | emergency | annual
            $t->string('pradesh', 120)->index();
            $t->string('district', 80)->nullable();
            $t->string('anchal', 120)->nullable();
            $t->string('venue', 200);
            $t->string('location', 300);
            $t->date('meeting_date')->index();
            $t->time('meeting_time')->nullable();
            $t->text('agenda')->nullable();
            $t->string('attachment', 255)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Existing State Sub-Admins get the meeting permissions (permissions are stored per user, so defaults alone do not reach them).
        $add = ['meetings.view', 'meetings.create', 'meetings.delete'];
        \App\Models\User::where('role', 'state_admin')->get()->each(function ($u) use ($add) {
            $u->permissions = array_values(array_unique(array_merge($u->permissions ?? [], $add)));
            $u->save();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_list');
    }
};
