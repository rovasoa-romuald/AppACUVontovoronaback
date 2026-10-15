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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('to');
            $table->string('from')->nullable();
            $table->string('status')->nullable();
            $table->text('message');
            $table->foreignId('user_to_id')->nullable()->constrained('users');
            $table->foreignId('user_from_id')->nullable()->constrained('users');
            $table->foreignId('template_id')->nullable()->constrained('template_messages');
            $table->json('log_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
