<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Academic\GradeBook\Cualitatives\CareerGuidance\CareerGuidance;
use App\Models\Academic\GradeBook\Cualitatives\ClassroomSupport\IntegralClassroomSupport;
use App\Models\Academic\GradeBook\Cualitatives\ReadingPromotion\ReadingPromotion;
use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Academic\GradeBook\Summaries\Supplementary\SupplementaryExam;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Services\Academic\PdfReportCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidación push centralizada de los reportes PDF de notas.
 *
 * Cualquier escritura Eloquent (saved/deleted/restored) sobre los modelos que
 * alimentan los reportes de calificaciones sube la versión de los buckets de
 * PdfReportCache (subject-grade, teacher y student). Como GradebookPdfService
 * embebe esas versiones en el nombre de archivo, el flujo async (ready()) deja
 * de servir el PDF viejo y regenera con los datos actualizados.
 *
 * NO cubre las escrituras masivas por BulkWrite::insertBatch/caseUpdate (no
 * disparan eventos Eloquent): esas rutas llaman explícitamente a las
 * invalidaciones (SaveQuickGradesAction, SaveAttendanceAction,
 * AttendanceService, AttendanceRegistrationService, GradeRegistrationService,
 * QualitativeGradeService, RecoveriesService).
 */
final class PdfReportCacheObserver
{
    public function __construct(private readonly PdfReportCache $pdfCache) {}

    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        match (true) {
            $model instanceof ActivityGrade => $this->invalidateActivityGrade($model),
            $model instanceof Activity => $this->invalidateActivity($model),
            $model instanceof AssessmentBlock => $this->invalidateAssessmentBlock($model),
            $model instanceof StudentExam,
            $model instanceof StudentProject,
            $model instanceof SupplementaryExam,
            $model instanceof CareerGuidance,
            $model instanceof IntegralClassroomSupport,
            $model instanceof ReadingPromotion => $this->invalidateSubjectGradeStudent(
                (int) $model->subject_id,
                (int) $model->grade_id,
                (int) $model->student_id,
            ),
            $model instanceof Attendance => $this->invalidateAttendance($model),
            default => null,
        };
    }

    private function invalidateActivityGrade(ActivityGrade $grade): void
    {
        $block = $grade->activity?->assessmentBlock;

        if (! $block) {
            return;
        }

        $this->invalidateStudentScope(
            subjectId: (int) $block->subject_id,
            gradeId: (int) $block->grade_id,
            teacherId: (int) $block->teacher_id,
            studentId: (int) $grade->student_id,
        );
    }

    private function invalidateActivity(Activity $activity): void
    {
        $block = $activity->assessmentBlock;

        if (! $block) {
            return;
        }

        $this->invalidateGradeScope(
            subjectId: (int) $block->subject_id,
            gradeId: (int) $block->grade_id,
            teacherId: (int) $block->teacher_id,
            yearId: (int) $block->year_id,
        );
    }

    private function invalidateAssessmentBlock(AssessmentBlock $block): void
    {
        $this->invalidateGradeScope(
            subjectId: (int) $block->subject_id,
            gradeId: (int) $block->grade_id,
            teacherId: (int) $block->teacher_id,
            yearId: (int) $block->year_id,
        );
    }

    private function invalidateAttendance(Attendance $attendance): void
    {
        $schedule = $attendance->classSchedule;

        if (! $schedule) {
            return;
        }

        $this->invalidateStudentScope(
            subjectId: (int) $schedule->subject_id,
            gradeId: (int) $schedule->grade_id,
            teacherId: (int) $schedule->teacher_id,
            studentId: (int) $attendance->student_id,
        );
    }

    /**
     * Invalida una nota individual: la asignatura-grado, el docente y el
     * estudiante afectado.
     */
    private function invalidateStudentScope(int $subjectId, int $gradeId, ?int $teacherId, int $studentId): void
    {
        if ($subjectId > 0 && $gradeId > 0) {
            $this->pdfCache->invalidateForSubjectGrade($subjectId, $gradeId);
        }

        if ($teacherId !== null && $teacherId > 0) {
            $this->pdfCache->invalidateForTeacher($teacherId);
        }

        if ($studentId > 0) {
            $this->pdfCache->invalidateForStudent($studentId);
        }
    }

    /**
     * Invalida un cambio de clase (crear/editar/eliminar bloque o actividad):
     * la asignatura-grado, el docente y TODOS los estudiantes matriculados en
     * el grado/año, porque los reportes individuales del tutor y del propio
     * estudiante dependen del bucket student.
     */
    private function invalidateGradeScope(int $subjectId, int $gradeId, ?int $teacherId, ?int $yearId): void
    {
        if ($subjectId > 0 && $gradeId > 0) {
            $this->pdfCache->invalidateForSubjectGrade($subjectId, $gradeId);

            $query = StudentEnrollment::query()->where('grade_id', $gradeId);

            if ($yearId !== null && $yearId > 0) {
                $query->where('year_id', $yearId);
            }

            $query->distinct()->pluck('student_id')->each(function (int $studentId): void {
                $this->pdfCache->invalidateForStudent($studentId);
            });
        }

        if ($teacherId !== null && $teacherId > 0) {
            $this->pdfCache->invalidateForTeacher($teacherId);
        }
    }

    /**
     * Invalida los reportes de la asignatura del estudiante afectado (sumas
     * e individuales de tutor), sin conocimiento del docente.
     */
    private function invalidateSubjectGradeStudent(int $subjectId, int $gradeId, int $studentId): void
    {
        $this->invalidateStudentScope($subjectId, $gradeId, null, $studentId);
    }
}
