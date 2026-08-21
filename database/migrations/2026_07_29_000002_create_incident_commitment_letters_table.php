<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_commitment_letters', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->integer('sequential_number');
            $table->string('type'); // academico, comportamental, asistencia
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('representative_id')->nullable()->constrained('representatives')->nullOnDelete();
            $table->foreignId('year_id')->constrained('scolar_years')->cascadeOnDelete();
            $table->foreignId('trimester_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->date('date');
            $table->text('commitments');
            $table->string('status')->default('draft'); // draft, signed, closed
            $table->date('signed_at')->nullable();
            $table->json('signatures')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'type']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_commitment_letters');
    }
};
