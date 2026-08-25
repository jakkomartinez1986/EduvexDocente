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
        Schema::create('channel_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->unique(); // whatsapp, telegram, email
            $table->string('provider'); // meta_cloud, telegram_bot, smtp
            $table->boolean('enabled')->default(false);
            $table->text('credentials')->nullable(); // encrypted JSON
            $table->string('sender_name')->nullable();
            $table->string('test_destination')->nullable();
            $table->string('test_status')->default('pending'); // pending, ok, failed
            $table->timestamp('tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_configurations');
    }
};
