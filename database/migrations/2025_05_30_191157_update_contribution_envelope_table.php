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
            $table->dateTime('contacted_at')->nullable();
            $table->dateTime('printed_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('money_ok_at')->nullable();
            $table->dateTime('lost_at')->nullable();
            $table->dateTime('not_reachable_at')->nullable();
            $table->dateTime('invalid_phone_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contribution_envelopes', function (Blueprint $table) {
            $table->dropColumn([
                'contacted_at',
                'printed_at',
                'delivered_at',
                'money_ok_at',
                'lost_at',
                'not_reachable_at',
                'invalid_phone_at',
            ]);
        });
    }
};
