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
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('year_id')->constrained('scolar_years');
            $table->date('enrollment_date');
            $table->date('completion_date')->nullable();
            $table->string('status')->default('active');
            $table->string('academic_year');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'grade_id', 'year_id'], 'enrollment_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
