<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->string('first_name')->nullable()->change();
            $table->string('civility')->nullable()->change();
            $table->integer('promotion_year')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            DB::table('user_details')->whereNull('first_name')->update(['first_name' => 'undefined']);
            DB::table('user_details')->whereNull('civility')->update(['civility' => 'undefined']);
            DB::table('user_details')->whereNull('promotion_year')->update(['promotion_year' => 0]);
            $table->string('first_name')->nullable(false)->change();
            $table->string('civility')->nullable(false)->change();
            $table->integer('promotion_year')->nullable(false)->change();
        });
    }
};
