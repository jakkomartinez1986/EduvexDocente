<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_interventions', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // academico, comportamental, asistencia
            $table->string('action_type');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('year_id')->constrained('scolar_years')->cascadeOnDelete();
            $table->foreignId('trimester_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->date('date');
            $table->text('description')->nullable();
            $table->string('status')->default('pending'); // pending, completed, programmed
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_interventions');
    }
};
