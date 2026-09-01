<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 1 (auditoría de capacidad): índices de consulta para los patrones
     * reales detectados en servicios y SFC Livewire.
     *
     * ADD: elimina seq scans en las consultas más calientes a escala
     * (150-250 concurrentes, 3.500 estudiantes).
     * DROP: índices redundantes (prefijo izquierdo de otro índice ya existente).
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->index('user_id', 'students_user_id_index');
        });

        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->index(['year_id', 'grade_id'], 'student_enrollments_year_grade_index');
        });

        Schema::table('attendance_summaries', function (Blueprint $table): void {
            $table->index(['student_id', 'year_id'], 'attendance_summaries_student_year_index');
        });

        Schema::table('homework_pendings', function (Blueprint $table): void {
            $table->index(['teacher_id', 'year_id', 'grade_id'], 'homework_pendings_teacher_year_grade_index');
            $table->index(['activity_id', 'student_id'], 'homework_pendings_activity_student_index');
        });

        Schema::table('academic_notifications', function (Blueprint $table): void {
            $table->index('student_id', 'academic_notifications_student_id_index');
            $table->index(['teacher_id', 'year_id'], 'academic_notifications_teacher_year_index');
        });

        Schema::table('representatives', function (Blueprint $table): void {
            $table->index('student_id', 'representatives_student_id_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index('status', 'users_status_index');
        });

        // Redundantes: son prefijo izquierdo de índices mayores ya existentes.
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropIndex('attendances_student_id_index');
        });

        Schema::table('student_exams', function (Blueprint $table): void {
            $table->dropIndex('student_exams_subject_id_grade_id_trimester_id_index');
        });

        Schema::table('student_projects', function (Blueprint $table): void {
            $table->dropIndex('student_projects_subject_id_grade_id_trimester_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropIndex('students_user_id_index');
        });

        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->dropIndex('student_enrollments_year_grade_index');
        });

        Schema::table('attendance_summaries', function (Blueprint $table): void {
            $table->dropIndex('attendance_summaries_student_year_index');
        });

        Schema::table('homework_pendings', function (Blueprint $table): void {
            $table->dropIndex('homework_pendings_teacher_year_grade_index');
            $table->dropIndex('homework_pendings_activity_student_index');
        });

        Schema::table('academic_notifications', function (Blueprint $table): void {
            $table->dropIndex('academic_notifications_student_id_index');
            $table->dropIndex('academic_notifications_teacher_year_index');
        });

        Schema::table('representatives', function (Blueprint $table): void {
            $table->dropIndex('representatives_student_id_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_status_index');
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->index('student_id', 'attendances_student_id_index');
        });

        Schema::table('student_exams', function (Blueprint $table): void {
            $table->index(['subject_id', 'grade_id', 'trimester_id'], 'student_exams_subject_id_grade_id_trimester_id_index');
        });

        Schema::table('student_projects', function (Blueprint $table): void {
            $table->index(['subject_id', 'grade_id', 'trimester_id'], 'student_projects_subject_id_grade_id_trimester_id_index');
        });
    }
};
