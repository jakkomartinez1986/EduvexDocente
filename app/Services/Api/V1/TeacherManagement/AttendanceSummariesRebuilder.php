<?php

declare(strict_types=1);

namespace App\Services\Api\V1\TeacherManagement;

use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\AttendanceSummary;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use Illuminate\Support\Collection;

/**
 * Materializa `attendance_summaries` por (student_id, grade_id, trimester_id,
 * year_id) agregando la asistencia de todos los docentes del curso
 * (database-optimization.md §7). Reutiliza el mismo criterio de conteo que
 * AttendanceSummaryService: total_classes = clases observadas en el período;
 * presentes = total_classes menos los registros explícitos de no-asistencia.
 */
final class AttendanceSummariesRebuilder
{
    private const ABANDONMENT_STATUSES = ['AI', 'AA'];

    /**
     * Reconstruye los resúmenes del año dado (o solo de un período).
     *
     * @param  int|null  $trimesterId  null = todos los períodos no-supletorio del año.
     */
    public function rebuild(int $yearId, ?int $trimesterId = null): void
    {
        foreach ($this->periods($yearId, $trimesterId) as $period) {
            $this->rebuildPeriod($yearId, $period);
        }
    }

    /**
     * @return iterable<int, AcademicPeriod>
     */
    private function periods(int $yearId, ?int $trimesterId): iterable
    {
        $query = AcademicPeriod::query()
            ->where('year_id', $yearId)
            ->where('is_supletorio', false);

        if ($trimesterId !== null) {
            $query->where('id', $trimesterId);
        }

        return $query->get();
    }

    private function rebuildPeriod(int $yearId, AcademicPeriod $period): void
    {
        $dateFrom = $period->start_date?->toDateString();
        $dateTo = $period->end_date?->toDateString();

        $schedulesByGrade = ClassSchedule::query()
            ->where('year_id', $yearId)
            ->get(['id', 'grade_id'])
            ->groupBy('grade_id');

        foreach ($schedulesByGrade as $gradeId => $schedules) {
            $this->rebuildGrade($yearId, (int) $gradeId, (int) $period->id, $dateFrom, $dateTo, $schedules);
        }
    }

    /**
     * @param  Collection<int, ClassSchedule>  $schedules
     */
    private function rebuildGrade(
        int $yearId,
        int $gradeId,
        int $periodId,
        ?string $dateFrom,
        ?string $dateTo,
        $schedules,
    ): void {
        $scheduleIds = $schedules->pluck('id');

        $totalClasses = ClassObservation::query()
            ->whereIn('class_schedule_id', $scheduleIds)
            ->when($dateFrom, fn ($query, $value) => $query->whereDate('observation_date', '>=', $value))
            ->when($dateTo, fn ($query, $value) => $query->whereDate('observation_date', '<=', $value))
            ->distinct()
            ->count('observation_date');

        $recordsByStudent = Attendance::query()
            ->whereIn('class_schedule_id', $scheduleIds)
            ->when($dateFrom, fn ($query, $value) => $query->whereDate('date', '>=', $value))
            ->when($dateTo, fn ($query, $value) => $query->whereDate('date', '<=', $value))
            ->get(['student_id', 'status'])
            ->groupBy('student_id');

        $studentIds = StudentEnrollment::query()
            ->where('year_id', $yearId)
            ->where('grade_id', $gradeId)
            ->pluck('student_id');

        foreach ($studentIds as $studentId) {
            $this->upsertStudent(
                (int) $studentId,
                $gradeId,
                $periodId,
                $yearId,
                $totalClasses,
                $recordsByStudent->get((int) $studentId) ?? collect(),
            );
        }
    }

    /**
     * @param  Collection<int, Attendance>  $records
     */
    private function upsertStudent(
        int $studentId,
        int $gradeId,
        int $periodId,
        int $yearId,
        int $totalClasses,
        $records,
    ): void {
        $late = $records->where('status', 'A')->count();
        $unjustified = $records->where('status', 'I')->count();
        $justified = $records->where('status', 'J')->count();
        $abandonment = $records->whereIn('status', self::ABANDONMENT_STATUSES)->count();

        $explicit = $late + $unjustified + $justified + $abandonment;
        $present = max(0, $totalClasses - $explicit);

        AttendanceSummary::updateOrCreate(
            [
                'student_id' => $studentId,
                'grade_id' => $gradeId,
                'trimester_id' => $periodId,
                'year_id' => $yearId,
            ],
            [
                'total_classes' => $totalClasses,
                'present_count' => $present,
                'late_count' => $late,
                'unjustified_count' => $unjustified,
                'justified_count' => $justified,
                'abandonment_count' => $abandonment,
                'last_updated' => now(),
            ],
        );
    }
}
