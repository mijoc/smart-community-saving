<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('province_code', 10)->nullable()->after('address');
            $table->string('district_code', 10)->nullable()->after('province_code');
            $table->string('sector_code', 10)->nullable()->after('district_code');
            $table->string('cell_code', 15)->nullable()->after('sector_code');
            $table->string('village_code', 20)->nullable()->after('cell_code');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->string('province_code', 10)->nullable()->after('country');
            $table->string('district_code', 10)->nullable()->after('province_code');
            $table->string('sector_code', 10)->nullable()->after('district_code');
            $table->string('cell_code', 15)->nullable()->after('sector_code');
            $table->string('village_code', 20)->nullable()->after('cell_code');
            $table->string('sector')->nullable()->after('district');
            $table->string('cell')->nullable()->after('sector');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['province_code', 'district_code', 'sector_code', 'cell_code', 'village_code']);
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['province_code', 'district_code', 'sector_code', 'cell_code', 'village_code', 'sector', 'cell']);
        });
    }
};
