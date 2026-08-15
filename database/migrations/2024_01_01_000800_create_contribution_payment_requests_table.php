<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained();
            $table->foreignId('group_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30)->default('mobile_money');
            $table->string('channel_ref', 120)->nullable();
            $table->date('paid_on');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending_review');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status']);
            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_payment_requests');
    }
};
