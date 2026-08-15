<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('name', 80);
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('name', 80);
            $table->string('province_code', 10)->index();
            $table->timestamps();
        });

        Schema::create('sectors', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('name', 80);
            $table->string('district_code', 10)->index();
            $table->timestamps();
        });

        Schema::create('cells', function (Blueprint $table) {
            $table->string('code', 15)->primary();
            $table->string('name', 80);
            $table->string('sector_code', 10)->index();
            $table->timestamps();
        });

        Schema::create('villages', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('name', 80);
            $table->string('cell_code', 15)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
        Schema::dropIfExists('cells');
        Schema::dropIfExists('sectors');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
    }
};
