<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $t) {
            $t->string('proof_file')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $t) {
            $t->dropColumn('proof_file');
        });
    }
};
