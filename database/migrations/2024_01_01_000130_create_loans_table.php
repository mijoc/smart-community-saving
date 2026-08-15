<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_id')->constrained()->cascadeOnDelete();
            $t->string('reference', 30)->unique();
            $t->decimal('principal', 14, 2);
            $t->decimal('interest_rate_pct', 6, 3)->default(5);   // flat % per month
            $t->unsignedSmallInteger('term_months')->default(3);
            $t->text('purpose')->nullable();
            $t->enum('status', [
                'requested', 'approved', 'rejected',
                'disbursed', 'repaying', 'paid',
                'defaulted', 'written_off',
            ])->default('requested');
            $t->date('requested_on');
            $t->date('approved_on')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->date('disbursed_on')->nullable();
            $t->date('due_on')->nullable();
            $t->decimal('total_interest', 14, 2)->default(0);
            $t->decimal('total_repayable', 14, 2)->default(0);
            $t->decimal('amount_repaid', 14, 2)->default(0);
            $t->decimal('outstanding', 14, 2)->default(0);
            $t->text('rejection_reason')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['group_id', 'status']);
            $t->index(['member_id', 'status']);
        });

        Schema::create('loan_repayments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 14, 2);
            $t->decimal('principal_portion', 14, 2)->default(0);
            $t->decimal('interest_portion', 14, 2)->default(0);
            $t->date('paid_on');
            $t->string('method', 30)->default('cash');
            $t->string('reference', 60)->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['loan_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('loans');
    }
};
