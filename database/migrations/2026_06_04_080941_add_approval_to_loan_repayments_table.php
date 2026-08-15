<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $t) {
            $t->string('status', 20)->default('approved')->after('notes');
            $t->string('payment_type', 20)->default('full')->after('status');
            $t->date('accrual_period')->nullable()->after('payment_type');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('accrual_period');
            $t->timestamp('approved_at')->nullable()->after('approved_by');
            $t->text('rejection_reason')->nullable()->after('approved_at');
            $t->index(['loan_id', 'status']);
        });

        \DB::table('loan_repayments')->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $t) {
            $t->dropColumn(['status', 'payment_type', 'accrual_period', 'approved_by', 'approved_at', 'rejection_reason']);
        });
    }
};
