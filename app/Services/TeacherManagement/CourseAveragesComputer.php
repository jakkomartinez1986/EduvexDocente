<?php

declare(strict_types=1);

namespace App\Services\TeacherManagement;

use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\GradingScheme;
use App\Services\Academic\GradeCalculationService;

/**
 * Cómputo de los agregados de una clase en un trimestre (source of truth,
 * cache-strategy.md §3). Las consultas de bloques/extones/proyectos y matriculados
 * viven aquí para que el job de recálculo y el SFC del gradebook usen la misma
 * lógica; la matemática se delega a GradeCalculationService::classTrimesterAggregates.
 */
final class CourseAveragesComputer
{
    public function __construct(private readonly GradeCalculationService $calculation) {}

    /**
     * Agregados de una clase/período: promedios formativo y total por alumno.
     *
     * @param  array<int, int>|null  $studentIds  Matriculados; si se omite se derivea de las matriculas del grado.
     * @return array{formative: array<int, float|null>, total: array<int, float|null>, hasData: bool}
     */
    public function compute(
        int $yearId,
        int $subjectId,
        int $gradeId,
        int $teacherId,
        int $trimesterId,
        ?GradingScheme $gradingScheme,
        ?array $studentIds = null,
    ): array {
        $studentIds ??= StudentEnrollment::query()
            ->where('year_id', $yearId)
            ->where('grade_id', $gradeId)
            ->pluck('student_id')
            ->unique()
            ->values()
            ->all();

        if ($studentIds === []) {
            return ['formative' => [], 'total' => [], 'hasData' => false];
        }

        $blocks = AssessmentBlock::query()
            ->where('year_id', $yearId)
            ->where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)
            ->where('trimester_id', $trimesterId)
            ->where('teacher_id', $teacherId)
            ->with(['activities.grades' => fn ($q) => $q->whereIn('student_id', $studentIds)])
            ->orderBy('order')
            ->get();

        $exams = StudentExam::query()
            ->where('year_id', $yearId)
            ->where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)
            ->where('trimester_id', $trimesterId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $projects = StudentProject::query()
            ->where('year_id', $yearId)
            ->where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)
            ->where('trimester_id', $trimesterId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        return $this->calculation->classTrimesterAggregates(
            $blocks,
            $exams,
            $projects,
            $studentIds,
            $gradingScheme,
        );
    }
}
