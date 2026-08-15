<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->timestamps();
            $t->index(['group_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('chat_last_seen_at')->nullable()->after('activities_last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('chat_last_seen_at'));
        Schema::dropIfExists('chat_messages');
    }
};
