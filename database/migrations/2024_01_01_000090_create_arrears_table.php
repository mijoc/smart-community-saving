<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('arrears', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('contribution_id')->constrained('contributions')->cascadeOnDelete();
            $table->decimal('outstanding_amount', 14, 2);
            $table->decimal('late_fee_applied', 14, 2)->default(0);
            $table->unsignedInteger('days_overdue')->default(0);
            $table->date('first_overdue_on');
            $table->date('last_evaluated_on');
            $table->enum('status', ['open', 'partially_cleared', 'cleared', 'waived'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status']);
            $table->unique(['contribution_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arrears');
    }
};
