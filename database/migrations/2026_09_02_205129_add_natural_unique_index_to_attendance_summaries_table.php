<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índice natural único para el upsert de RebuildAttendanceSummaries y el
     * acceso por (grade_id, trimester_id, year_id) (database-optimization.md §7).
     */
    public function up(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'grade_id', 'trimester_id', 'year_id'],
                'attendance_summaries_student_grade_trimester_year_unique',
            );
            $table->index(
                ['grade_id', 'trimester_id', 'year_id'],
                'attendance_summaries_grade_trimester_year_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->dropUnique('attendance_summaries_student_grade_trimester_year_unique');
            $table->dropIndex('attendance_summaries_grade_trimester_year_index');
        });
    }
};
