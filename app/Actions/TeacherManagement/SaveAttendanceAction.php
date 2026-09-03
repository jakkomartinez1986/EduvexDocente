<?php

declare(strict_types=1);

namespace App\Actions\TeacherManagement;

use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Services\Academic\PdfReportCache;

final class SaveAttendanceAction
{
    public function __construct(private readonly PdfReportCache $pdfCache) {}

    public function handle(
        int $scheduleId,
        string $date,
        int $yearId,
        int $userId,
        array $statuses,
        ?string $classtopic = null,
        ?string $observation = null,
        ?string $novedad = null,
        ?string $novedadType = null,
    ): void {
        $calendarDay = CalendarDay::where('date', $date)->first();

        $observationModel = ClassObservation::updateOrCreate(
            [
                'class_schedule_id' => $scheduleId,
                'observation_date' => $date,
            ],
            [
                'teacher_id' => auth()->user()->teacher?->id,
                'year_id' => $yearId,
                'classtopic' => $classtopic,
                'observation' => $observation ?: 'Asistencia tomada desde el horario del docente.',
                'class_observation' => $observation,
                'novedad' => $novedad,
                'novedad_type' => $novedadType,
            ]
        );

        foreach ($statuses as $studentId => $status) {
            if ($status === 'P' || $status === '') {
                continue;
            }

            Attendance::updateOrCreate(
                [
                    'class_schedule_id' => $scheduleId,
                    'student_id' => $studentId,
                    'date' => $date,
                ],
                [
                    'class_observation_id' => $observationModel->id,
                    'calendarday_id' => $calendarDay?->id,
                    'year_id' => $yearId,
                    'status' => trim($status),
                    'recorded_by' => $userId,
                ]
            );
        }

        $schedule = ClassSchedule::find($scheduleId);
        if ($schedule !== null) {
            $this->pdfCache->invalidateForSubjectGrade((int) $schedule->subject_id, (int) $schedule->grade_id);
        }
        foreach (array_keys($statuses) as $studentId) {
            $this->pdfCache->invalidateForStudent((int) $studentId);
        }
    }
}
