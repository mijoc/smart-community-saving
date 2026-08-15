<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('village')->nullable();
            $table->string('district')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->string('currency', 8)->default('UGX');
            $table->date('formed_on')->nullable();
            $table->date('cycle_starts_on')->nullable();
            $table->date('cycle_ends_on')->nullable();
            $table->enum('status', ['active', 'paused', 'closed'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
