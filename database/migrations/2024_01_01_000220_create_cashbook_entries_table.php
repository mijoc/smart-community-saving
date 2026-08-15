<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cashbook_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 60)->unique();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->string('category', 60);
            $table->decimal('amount', 14, 2);
            $table->enum('method', ['cash', 'mobile_money', 'bank', 'cheque', 'other'])->default('cash');
            $table->string('channel_ref', 120)->nullable();
            $table->string('counterparty', 160)->nullable();
            $table->date('occurred_on');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['group_id', 'type']);
            $table->index('occurred_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashbook_entries');
    }
};
