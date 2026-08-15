<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_interest_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->decimal('balance_before', 14, 2);
            $table->decimal('rate_pct', 6, 3);
            $table->decimal('interest_amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->timestamps();

            $table->unique(['loan_id', 'period']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->string('interest_model', 20)->default('compound')->after('interest_rate_pct');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_interest_accruals');
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('interest_model');
        });
    }
};
