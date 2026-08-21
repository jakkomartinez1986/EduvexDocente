<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Academic;

use App\Http\Resources\Api\V1\Academic\AssessmentBlockResource;
use App\Http\Resources\Api\V1\Academic\StudentExamResource;
use App\Http\Resources\Api\V1\Academic\StudentProjectResource;
use App\Http\Resources\Api\V1\Academic\SupplementaryExamResource;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Academic\GradeBook\Summaries\Supplementary\SupplementaryExam;
use App\Models\Identity\Users\Teacher;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;

/**
 * Descarga el libro de calificaciones del docente (bloques, actividades,
 * notas, exámenes y proyectos) para trabajo offline.
 */
final class GradebookDownloadService
{
    public function __construct(private readonly AcademicYearService $academicYearService) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function download(Teacher $teacher, array $filters): array
    {
        $yearId = $filters['year_id'] ?? $this->academicYearService->getActiveYearId();

        if ($yearId === null) {
            return [
                'year_id' => null,
                'generated_at' => now()->toISOString(),
                'blocks' => [],
                'exams' => [],
                'projects' => [],
                'supplementary_exams' => [],
            ];
        }

        $yearId = (int) $yearId;

        $gradeIds = ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->where('year_id', $yearId)
            ->pluck('grade_id')
            ->unique()
            ->values();

        $subjectIds = ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->where('year_id', $yearId)
            ->pluck('subject_id')
            ->unique()
            ->values();

        $blocks = AssessmentBlock::query()
            ->with(['activities.grades'])
            ->where('year_id', $yearId)
            ->where('teacher_id', $teacher->id)
            ->when($filters['subject_id'] ?? null, fn ($query, $value) => $query->where('subject_id', $value))
            ->when($filters['grade_id'] ?? null, fn ($query, $value) => $query->where('grade_id', $value))
            ->when($filters['trimester_id'] ?? null, fn ($query, $value) => $query->where('trimester_id', $value))
            ->orderBy('order')
            ->get();

        $exams = StudentExam::query()
            ->where('year_id', $yearId)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('grade_id', $gradeIds)
            ->when($filters['subject_id'] ?? null, fn ($query, $value) => $query->where('subject_id', $value))
            ->when($filters['grade_id'] ?? null, fn ($query, $value) => $query->where('grade_id', $value))
            ->when($filters['trimester_id'] ?? null, fn ($query, $value) => $query->where('trimester_id', $value))
            ->get();

        $projects = StudentProject::query()
            ->where('year_id', $yearId)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('grade_id', $gradeIds)
            ->when($filters['subject_id'] ?? null, fn ($query, $value) => $query->where('subject_id', $value))
            ->when($filters['grade_id'] ?? null, fn ($query, $value) => $query->where('grade_id', $value))
            ->when($filters['trimester_id'] ?? null, fn ($query, $value) => $query->where('trimester_id', $value))
            ->get();

        $supplementaryExams = SupplementaryExam::query()
            ->where('year_id', $yearId)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('grade_id', $gradeIds)
            ->when($filters['subject_id'] ?? null, fn ($query, $value) => $query->where('subject_id', $value))
            ->when($filters['grade_id'] ?? null, fn ($query, $value) => $query->where('grade_id', $value))
            ->get();

        return [
            'year_id' => $yearId,
            'generated_at' => now()->toISOString(),
            'blocks' => AssessmentBlockResource::collection($blocks),
            'exams' => StudentExamResource::collection($exams),
            'projects' => StudentProjectResource::collection($projects),
            'supplementary_exams' => SupplementaryExamResource::collection($supplementaryExams),
        ];
    }
}
