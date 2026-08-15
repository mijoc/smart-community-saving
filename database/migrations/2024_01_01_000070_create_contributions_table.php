<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('contribution_schedule_id')->nullable()->constrained('contribution_schedules')->nullOnDelete();
            $table->enum('type', ['savings', 'social_fund', 'loan_repayment', 'fine', 'late_fee', 'other'])->default('savings');
            $table->decimal('expected_amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('late_fee_amount', 14, 2)->default(0);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_on');
            $table->date('paid_on')->nullable();
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'waived'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['group_id', 'member_id', 'due_on']);
            $table->index(['status', 'due_on']);
            $table->unique(['group_id', 'member_id', 'contribution_schedule_id', 'period_start', 'type'], 'contrib_unique_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
