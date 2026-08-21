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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_block_id')->constrained('assessment_blocks')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('date')->nullable();
            $table->decimal('max_score', 5, 2)->default(10);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('assessment_block_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
