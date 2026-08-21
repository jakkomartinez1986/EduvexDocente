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

        Schema::create('integral_classroom_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('skill_id')->constrained('integral_classroom_support_indicators');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('grade_id')->constrained('grades');
            $table->foreignId('trimester_id')->constrained('academic_periods');
            $table->foreignId('year_id')->constrained('scolar_years');
            $table->string('value', 1)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['student_id', 'skill_id', 'subject_id', 'grade_id', 'trimester_id', 'year_id'], 'integral_classroom_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integral_classroom_supports');
    }
};
