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
        Schema::create('contribution_envelopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users');
            $table->foreignId('responsible_id')->constrained('users');
            $table->foreignId('assistant_responsible_id')->nullable()->constrained('users');
            $table->string('status')->default('to_be_delivered');
            $table->string('mean_of_delivery')->default('one_to_one');
            $table->float('earnings')->default(0.0)->nullable();
            $table->string('qrcode_image_path')->nullable();
            $table->string('reason');
            $table->json('history_json')->nullable();
            $table->text('commentary')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contribution_envelopes');
    }
};
