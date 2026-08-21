<?php

declare(strict_types=1);

namespace App\Services\Api\V1\TeacherManagement;

use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Models\User;
use App\Services\AcademicYearService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resumen de asistencias por período del docente: conteos por estudiante y
 * estado (atraso, faltas justificadas/injustificadas, abandono) sobre sus
 * horarios del año. Los presentes se derivan de las clases efectivamente
 * impartidas (observaciones de clase) sin registro de no-asistencia.
 */
final class AttendanceSummaryService
{
    private const ABANDONMENT_STATUSES = ['AI', 'AA'];

    public function __construct(private readonly AcademicYearService $academicYearService) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function summary(Teacher $teacher, array $validated): array
    {
        $yearId = $this->resolveYearId($validated);
        $year = ScolarYear::find($yearId);

        [$dateFrom, $dateTo, $period] = $this->resolvePeriod($validated, $yearId, $year);

        $schedules = $this->schedules($teacher, $yearId, $validated);

        if ($schedules->isEmpty()) {
            return [
                'generated_at' => now()->toISOString(),
                'period' => $period,
                'students' => [],
                'totals' => $this->emptyTotals(),
            ];
        }

        $scheduleIds = $schedules->pluck('id');

        $observationDates = ClassObservation::query()
            ->whereIn('class_schedule_id', $scheduleIds)
            ->when($dateFrom, fn ($query, $value) => $query->whereDate('observation_date', '>=', $value))
            ->when($dateTo, fn ($query, $value) => $query->whereDate('observation_date', '<=', $value))
            ->get(['class_schedule_id', 'observation_date']);

        $recordsByStudent = Attendance::query()
            ->whereIn('class_schedule_id', $scheduleIds)
            ->when($dateFrom, fn ($query, $value) => $query->whereDate('date', '>=', $value))
            ->when($dateTo, fn ($query, $value) => $query->whereDate('date', '<=', $value))
            ->get(['student_id', 'status']);

        $students = $this->students($yearId, $schedules->pluck('grade_id')->unique()->values());

        $rows = $students->map(
            fn (Student $student): array => $this->studentRow(
                $student,
                $schedules,
                $observationDates,
                $recordsByStudent,
            ),
        )->values()->all();

        return [
            'generated_at' => now()->toISOString(),
            'period' => $period,
            'students' => $rows,
            'totals' => $this->totals($rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveYearId(array $validated): int
    {
        if (($validated['year_id'] ?? null) !== null) {
            return (int) $validated['year_id'];
        }

        $activeYearId = $this->academicYearService->getActiveYearId();

        if ($activeYearId === null) {
            throw new NotFoundHttpException('No existe un año lectivo activo.');
        }

        return $activeYearId;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string|null, 1: string|null, 2: array<string, mixed>}
     */
    private function resolvePeriod(array $validated, int $yearId, ?ScolarYear $year): array
    {
        $dateFrom = null;
        $dateTo = null;

        if (($validated['trimester_id'] ?? null) !== null) {
            $period = AcademicPeriod::find((int) $validated['trimester_id']);

            if (! $period || (int) $period->year_id !== $yearId || (bool) $period->is_supletorio) {
                throw new NotFoundHttpException('El período no es válido para el resumen de asistencias.');
            }

            $dateFrom = $period->start_date ? Carbon::parse($period->start_date)->toDateString() : null;
            $dateTo = $period->end_date ? Carbon::parse($period->end_date)->toDateString() : null;

            return [
                $dateFrom,
                $dateTo,
                [
                    'year_id' => $yearId,
                    'year_name' => $year?->year_name,
                    'trimester_id' => $period->id,
                    'trimester_name' => $period->trimester_name,
                    'is_supletorio' => false,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ];
        }

        if (($validated['date_from'] ?? null) !== null) {
            $dateFrom = (string) Carbon::parse($validated['date_from'])->toDateString();
        }

        if (($validated['date_to'] ?? null) !== null) {
            $dateTo = (string) Carbon::parse($validated['date_to'])->toDateString();
        }

        if ($dateFrom !== null && $dateTo !== null && $dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'La fecha final debe ser posterior o igual a la fecha inicial.',
            ]);
        }

        return [
            $dateFrom,
            $dateTo,
            [
                'year_id' => $yearId,
                'year_name' => $year?->year_name,
                'trimester_id' => null,
                'trimester_name' => null,
                'is_supletorio' => null,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return Collection<int, ClassSchedule>
     */
    private function schedules(Teacher $teacher, int $yearId, array $validated): Collection
    {
        $query = ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->where('year_id', $yearId);

        if (($validated['schedule_id'] ?? null) !== null) {
            $query->where('id', (int) $validated['schedule_id']);
        }

        $schedules = $query->get();

        if (($validated['schedule_id'] ?? null) !== null && $schedules->count() !== 1) {
            throw new NotFoundHttpException('No se encontró el horario de clase para este docente.');
        }

        return $schedules;
    }

    /**
     * @param  Collection<int, int>  $gradeIds
     * @return Collection<int, Student>
     */
    private function students(int $yearId, Collection $gradeIds): Collection
    {
        $studentIds = StudentEnrollment::query()
            ->where('year_id', $yearId)
            ->whereIn('grade_id', $gradeIds)
            ->pluck('student_id')
            ->unique()
            ->values();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        return Student::with('user')
            ->whereIn('id', $studentIds)
            ->orderBy(User::select('lastname')->whereColumn('users.id', 'students.user_id'))
            ->orderBy(User::select('name')->whereColumn('users.id', 'students.user_id'))
            ->get();
    }

    /**
     * @param  Collection<int, ClassSchedule>  $schedules
     * @param  Collection<int, ClassObservation>  $observationDates
     * @param  Collection<int, Attendance>  $recordsByStudent
     * @return array<string, mixed>
     */
    private function studentRow(
        Student $student,
        Collection $schedules,
        Collection $observationDates,
        Collection $recordsByStudent,
    ): array {
        $totalClasses = $schedules->sum(
            fn (ClassSchedule $schedule): int => $observationDates->where('class_schedule_id', $schedule->id)->count(),
        );

        $records = $recordsByStudent->where('student_id', $student->id);
        $late = $records->where('status', 'A')->count();
        $unjustified = $records->where('status', 'I')->count();
        $justified = $records->where('status', 'J')->count();
        $abandonment = $records->whereIn('status', self::ABANDONMENT_STATUSES)->count();

        $explicit = $late + $unjustified + $justified + $abandonment;
        $present = max(0, $totalClasses - $explicit);

        return [
            'student_id' => $student->id,
            'name' => $student->full_name,
            'code' => $student->student_code,
            'total_classes' => $totalClasses,
            'present_count' => $present,
            'late_count' => $late,
            'unjustified_count' => $unjustified,
            'justified_count' => $justified,
            'abandonment_count' => $abandonment,
            'attendance_rate' => $totalClasses > 0 ? round($present / $totalClasses * 100, 2) : 0.0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function totals(array $rows): array
    {
        $totals = $this->emptyTotals();

        foreach ($rows as $row) {
            $totals['total_classes'] += (int) $row['total_classes'];
            $totals['present_count'] += (int) $row['present_count'];
            $totals['late_count'] += (int) $row['late_count'];
            $totals['unjustified_count'] += (int) $row['unjustified_count'];
            $totals['justified_count'] += (int) $row['justified_count'];
            $totals['abandonment_count'] += (int) $row['abandonment_count'];
        }

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    private function emptyTotals(): array
    {
        return [
            'total_classes' => 0,
            'present_count' => 0,
            'late_count' => 0,
            'unjustified_count' => 0,
            'justified_count' => 0,
            'abandonment_count' => 0,
        ];
    }
}
