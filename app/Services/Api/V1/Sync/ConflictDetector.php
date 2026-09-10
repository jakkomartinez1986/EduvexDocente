<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Sync;

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Academic\GradeBook\Summaries\Supplementary\SupplementaryExam;
use App\Models\Identity\Users\Teacher;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Services\AcademicYearService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Detector de conflictos §7.6 (API_ROADMAP): compara `base_updated_at` del
 * cliente contra el estado REAL y PREVIO del servidor. Debe ejecutarse
 * SIEMPRE antes de aplicar la operación.
 *
 * Política por entidad:
 * - Notas (actividad/sumativa/supletorio): otro autor + más nuevo + valor
 *   distinto ⇒ conflict; mismo autor ⇒ same_author_newer (newest-wins con aviso).
 * - Asistencia (día completo): snapshot gana; de otro autor ⇒ conflict informativo.
 * - Fuera de ventana de calificación: regla dura, vive en los servicios REST
 *   (nunca tiene override, ni siquiera con force).
 */
final class ConflictDetector
{
    public function __construct(private readonly AcademicYearService $academicYearService) {}

    /**
     * @param  array<string, mixed>  $payload  payload validado de la operación
     */
    public function detect(Teacher $teacher, string $entity, array $payload): ?ConflictOutcome
    {
        $baseUpdatedAt = isset($payload['base_updated_at'])
            ? Carbon::parse((string) $payload['base_updated_at'])
            : null;

        if ($baseUpdatedAt === null) {
            return null;
        }

        if ($entity === 'attendance_day') {
            return $this->detectAttendanceOutcome($teacher, $payload, $baseUpdatedAt);
        }

        return $this->detectGradesOutcome($teacher, $entity, $payload, $baseUpdatedAt);
    }

    /**
     * Asistencia: la snapshot completa gana siempre que sea del mismo autor;
     * si el día fue cambiado por OTRO autor hay conflicto informativo.
     *
     * @param  array<string, mixed>  $payload
     */
    private function detectAttendanceOutcome(Teacher $teacher, array $payload, Carbon $baseUpdatedAt): ?ConflictOutcome
    {
        $observation = ClassObservation::query()
            ->where('class_schedule_id', (int) $payload['schedule_id'])
            ->whereDate('observation_date', (string) $payload['date'])
            ->first();

        if (! $observation instanceof ClassObservation || ! $observation->updated_at->greaterThan($baseUpdatedAt)) {
            return null;
        }

        if ((int) $observation->teacher_id === $teacher->id) {
            return ConflictOutcome::sameAuthorNewer();
        }

        return ConflictOutcome::conflict([
            'schedule_id' => (int) $observation->class_schedule_id,
            'date' => (string) $observation->observation_date,
            'classtopic' => $observation->classtopic,
            'updated_at' => $observation->updated_at->toISOString(),
        ]);
    }

    /**
     * Notas: conflicto duro si alguna fila objetivo fue escrita por otro
     * autor, es más nueva que base y difiere del valor entrante; aviso si
     * la fila más nueva es del propio docente (multi-dispositivo).
     *
     * @param  array<string, mixed>  $payload
     */
    private function detectGradesOutcome(Teacher $teacher, string $entity, array $payload, Carbon $baseUpdatedAt): ?ConflictOutcome
    {
        /** @var Model|null $conflictingRow */
        $conflictingRow = null;

        /** @var Model|null $sameAuthorRow */
        $sameAuthorRow = null;

        foreach ($this->targetGradeRows($entity, $payload) as $row) {
            $incoming = collect((array) $payload['grades'])->firstWhere('student_id', (int) $row->student_id);

            if ($incoming === null || ! $row->updated_at->greaterThan($baseUpdatedAt)) {
                continue;
            }

            if (! $this->gradeDiffers($row->grade, $incoming['grade'])) {
                continue;
            }

            if ((int) $row->recorded_by !== $teacher->user_id) {
                $conflictingRow ??= $row;
            } else {
                $sameAuthorRow ??= $row;
            }
        }

        if ($conflictingRow !== null) {
            return ConflictOutcome::conflict([
                'student_id' => (int) $conflictingRow->student_id,
                'grade' => $conflictingRow->grade !== null ? (float) $conflictingRow->grade : null,
                'updated_at' => $conflictingRow->updated_at->toISOString(),
            ]);
        }

        if ($sameAuthorRow !== null) {
            return ConflictOutcome::sameAuthorNewer();
        }

        return null;
    }

    /**
     * Filas de notas objetivo según la entidad, resueltas sobre las claves
     * naturales del payload (sin aplicar nada).
     *
     * @param  array<string, mixed>  $payload
     * @return iterable<ActivityGrade|StudentProject|StudentExam|SupplementaryExam>
     */
    private function targetGradeRows(string $entity, array $payload): iterable
    {
        $studentIds = collect((array) $payload['grades'])
            ->pluck('student_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        $rows = match ($entity) {
            'activity_grade' => ActivityGrade::query()
                ->where('activity_id', (int) $payload['activity_id'])
                ->whereIn('student_id', $studentIds),
            'summative_grades' => $this->summativeQuery($payload, $studentIds),
            default => $this->supplementaryQuery($payload, $studentIds),
        };

        return $rows->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, int>  $studentIds
     * @return Builder<StudentProject>|Builder<StudentExam>
     */
    private function summativeQuery(array $payload, Collection $studentIds): mixed
    {
        return ($payload['type'] === 'project' ? StudentProject::query() : StudentExam::query())
            ->where('subject_id', (int) $payload['subject_id'])
            ->where('grade_id', (int) $payload['grade_id'])
            ->where('trimester_id', (int) $payload['trimester_id'])
            ->where('year_id', $this->resolveYearId($payload))
            ->whereIn('student_id', $studentIds);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, int>  $studentIds
     * @return Builder<SupplementaryExam>
     */
    private function supplementaryQuery(array $payload, Collection $studentIds): mixed
    {
        return SupplementaryExam::query()
            ->where('subject_id', (int) $payload['subject_id'])
            ->where('grade_id', (int) $payload['grade_id'])
            ->where('year_id', $this->resolveYearId($payload))
            ->whereIn('student_id', $studentIds);
    }

    /**
     * Comparación de notas a 2 decimales (columna numeric(5,2)).
     */
    private function gradeDiffers(mixed $stored, mixed $incoming): bool
    {
        if ($stored === null && ($incoming === null || $incoming === '')) {
            return false;
        }

        if ($stored === null || $incoming === null || $incoming === '') {
            return true;
        }

        return round((float) $stored, 2) !== round((float) $incoming, 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveYearId(array $payload): int
    {
        if (($payload['year_id'] ?? null) !== null) {
            return (int) $payload['year_id'];
        }

        $activeYearId = $this->academicYearService->getActiveYearId();

        if ($activeYearId === null) {
            throw ValidationException::withMessages([
                'year_id' => 'No existe un año lectivo activo.',
            ]);
        }

        return $activeYearId;
    }
}
