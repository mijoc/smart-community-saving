<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('passbook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('description');
            $table->enum('category', ['savings', 'social_fund', 'loan', 'loan_repayment', 'fine', 'late_fee', 'withdrawal', 'share_out', 'other'])->default('savings');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->morphs('source');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['group_id', 'member_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passbook_entries');
    }
};
