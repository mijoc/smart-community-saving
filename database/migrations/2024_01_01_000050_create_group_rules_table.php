<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('value')->nullable();
            $table->enum('type', ['numeric', 'percent', 'days', 'string', 'boolean'])->default('string');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['group_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_rules');
    }
};
