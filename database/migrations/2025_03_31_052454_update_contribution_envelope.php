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
        Schema::table('contribution_envelopes', function (Blueprint $table) {
            $table->foreignId('recipient_id')->nullable()->change();
            $table->foreignId('responsible_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contribution_envelopes', function (Blueprint $table) {
            $table->foreignId('recipient_id')->nullable(false)->change();
            $table->foreignId('responsible_id')->nullable(false)->change();
        });
    }
};
