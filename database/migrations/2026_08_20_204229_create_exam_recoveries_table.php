<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('trimester_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->foreignId('year_id')->nullable()->constrained('scolar_years')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->decimal('original_grade', 5, 2);
            $table->decimal('recovery_grade', 5, 2);
            $table->string('update_method', 20);
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->boolean('is_applied')->default(false);
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'subject_id', 'grade_id', 'trimester_id', 'year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_recoveries');
    }
};
