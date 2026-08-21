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
        Schema::create('academic_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->integer('notification_number')->nullable();
            $table->string('type'); // asistencia (Inasistencia,Atraso,Riesgo de abandono institucional)-academico(Tareas incumplidas,Desempeño en clases,Bajo rendimiento académico,Incumplimiento de actividades,Falta de materiales) - comportamental (Comportamiento,Relaciones con sus compañeros,Uniforme y normas de aseo,Irrespeto,Indisciplina)
            $table->string('channel');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('year_id')->constrained('scolar_years')->cascadeOnDelete();
            $table->foreignId('trimester_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->text('message');
            $table->jsonb('motives')->nullable();
            $table->text('observation')->nullable();
            $table->date('appointment_date')->nullable();
            $table->time('appointment_time')->nullable();
            $table->date('generated_date')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('summoned_at')->nullable();
            $table->boolean('parent_attended')->nullable();
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_notifications');
    }
};
