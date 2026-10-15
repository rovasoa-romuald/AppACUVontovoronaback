<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('censuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_major')->constrained('majors');
            $table->foreignId('id_level')->constrained('levels');
            $table->foreignId('id_user')->nullable()->constrained('users');
            $table->boolean('is_stay_weekend', true);
            $table->year('census_year')->nullable();
            $table->integer('block')->nullable();
            $table->integer('door_number')->nullable();
            $table->boolean('is_off_campus', false);
            // adapt it with the selection of major with id_major, level with id_level, student with id_user, census_year, block, door_number, is_off_campus, is_stay_weekend
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('censuses');
    }
};
