<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The rotation plan itself.
        Schema::create('rotations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained()->cascadeOnDelete();
            $t->string('name', 160);

            // Cadence of payouts.
            $t->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');

            // How many members receive a share per turn.
            $t->unsignedSmallInteger('recipients_per_turn')->default(1);

            // How the disbursement amount is calculated each turn:
            //   full       -> distribute the whole cash-on-hand
            //   percentage -> distribute (disbursement_pct %) of the cash-on-hand
            //   fixed      -> distribute a fixed amount (disbursement_amount)
            $t->enum('disbursement_method', ['full', 'percentage', 'fixed'])->default('full');
            $t->decimal('disbursement_pct',    6, 3)->nullable();
            $t->decimal('disbursement_amount', 14, 2)->nullable();

            $t->date('starts_on');
            $t->date('next_turn_on')->nullable();

            $t->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['group_id', 'status']);
        });

        // Ordered recipient list. position is 1-based.
        Schema::create('rotation_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rotation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('position');
            $t->unsignedSmallInteger('received_count')->default(0);
            $t->date('last_received_on')->nullable();
            $t->timestamps();

            $t->unique(['rotation_id', 'member_id']);
            $t->index(['rotation_id', 'position']);
        });

        // Each scheduled disbursement event for a rotation.
        Schema::create('rotation_turns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rotation_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('sequence_no');
            $t->date('scheduled_on');
            $t->enum('status', ['scheduled', 'paid', 'skipped'])->default('scheduled');
            $t->decimal('disbursement_total', 14, 2)->default(0);
            $t->date('executed_on')->nullable();
            $t->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->unique(['rotation_id', 'sequence_no']);
            $t->index(['rotation_id', 'status']);
            $t->index('scheduled_on');
        });

        // Actual money handed to a member during a turn.
        Schema::create('rotation_payouts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rotation_turn_id')->constrained()->cascadeOnDelete();
            $t->foreignId('rotation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 14, 2);
            $t->date('paid_on');
            $t->enum('method', ['cash', 'mobile_money', 'bank', 'cheque', 'other'])->default('cash');
            $t->string('reference', 60)->nullable();
            $t->foreignId('cashbook_entry_id')->nullable()->constrained('cashbook_entries')->nullOnDelete();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index(['group_id', 'member_id']);
            $t->index(['rotation_turn_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotation_payouts');
        Schema::dropIfExists('rotation_turns');
        Schema::dropIfExists('rotation_members');
        Schema::dropIfExists('rotations');
    }
};
