<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 60)->index();
            $t->string('icon', 40)->nullable();
            $t->string('color', 20)->nullable();
            $t->text('description');
            $t->nullableMorphs('subject');
            $t->json('data')->nullable();
            $t->timestamps();
            $t->index(['group_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('activities_last_seen_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('activities_last_seen_at'));
        Schema::dropIfExists('activities');
    }
};
