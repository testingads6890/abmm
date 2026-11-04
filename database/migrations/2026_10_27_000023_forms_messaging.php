<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Milestone A / 3: online form builder + approval workflow, messaging opt-in & log, translations. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('form_definitions', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique();
            $t->string('title', 150);
            $t->text('description')->nullable();
            $t->json('fields');                          // [{key,label,type,required,options[]}]
            $t->decimal('fee', 10, 2)->default(0);
            $t->string('currency', 3)->default('INR');
            $t->boolean('needs_approval')->default(true);
            $t->string('country', 100)->nullable();      // visibility scope (null = all)
            $t->string('city', 150)->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('form_submissions', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->foreignId('form_id')->constrained('form_definitions')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->index();
            $t->foreignId('family_id')->nullable()->index();
            $t->string('country', 100)->nullable();
            $t->string('state', 150)->nullable();
            $t->string('city', 150)->nullable();
            $t->string('zone', 100)->nullable();
            $t->json('data');
            $t->string('status', 12)->default('pending'); // pending | approved | rejected
            $t->string('payment_status', 10)->default('none'); // none | due | paid
            $t->foreignId('payment_id')->nullable();
            $t->string('remark', 255)->nullable();
            $t->foreignId('decided_by')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'country', 'city']);
        });

        Schema::create('message_optins', function (Blueprint $t) {
            $t->id();
            $t->string('mobile', 20)->index();
            $t->string('channel', 10);                   // whatsapp | sms | email
            $t->boolean('opted_in')->default(true);
            $t->string('source', 20)->default('app');
            $t->timestamps();
            $t->unique(['mobile', 'channel']);
        });

        Schema::create('message_logs', function (Blueprint $t) {
            $t->id();
            $t->string('channel', 10);
            $t->string('category', 12)->default('utility'); // utility | marketing | auth
            $t->string('to', 150);
            $t->string('template', 80)->nullable();
            $t->text('body')->nullable();
            $t->string('status', 10)->default('queued');   // queued | sent | failed | skipped
            $t->string('provider_id', 120)->nullable();
            $t->text('error')->nullable();
            $t->decimal('cost', 8, 4)->default(0);
            $t->foreignId('family_id')->nullable()->index();
            $t->timestamps();
            $t->index(['channel', 'status', 'created_at']);
        });

        Schema::create('translations', function (Blueprint $t) {
            $t->id();
            $t->string('locale', 8);
            $t->string('key', 190);
            $t->text('value');
            $t->timestamps();
            $t->unique(['locale', 'key']);
        });
    }

    public function down(): void
    {
        foreach (['translations', 'message_logs', 'message_optins', 'form_submissions', 'form_definitions'] as $x) Schema::dropIfExists($x);
    }
};
