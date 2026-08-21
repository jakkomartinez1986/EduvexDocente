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
        Schema::create('calendar_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('year_id')->constrained('scolar_years')->cascadeOnDelete();
            $table->foreignId('trimester_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->string('period', 20)->nullable();
            $table->date('date');
            $table->string('month_name', 20)->nullable();
            $table->string('day_name', 20);
            $table->integer('week')->nullable();
            $table->integer('day_number')->nullable();
            $table->string('activity')->nullable();
            $table->boolean('is_holiday')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['year_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_days');
    }
};
