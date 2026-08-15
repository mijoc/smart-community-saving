<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contribution_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['savings', 'social_fund', 'loan_repayment', 'fine', 'other'])->default('savings');
            $table->enum('frequency', ['weekly', 'fortnightly', 'monthly', 'quarterly'])->default('weekly');
            $table->decimal('amount', 14, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_due_on')->nullable();
            $table->date('last_generated_on')->nullable();
            $table->unsignedTinyInteger('grace_days')->default(2);
            $table->decimal('late_fee_pct', 6, 2)->default(0);
            $table->decimal('late_fee_flat', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['group_id', 'is_active']);
            $table->index('next_due_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_schedules');
    }
};
