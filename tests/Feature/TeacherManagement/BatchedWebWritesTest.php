<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Services\TeacherManagement\AttendanceService;
use App\Services\TeacherManagement\QuickGradesService;

function webWriteContext(): array
{
    return syncGradebookContext();
}

it('crea filas solo para estados no-P y actualiza en lote al re-guardar', function (): void {
    $context = webWriteContext();
    [$studentA, $studentB, $studentC] = $context['students'];
    $teacherUser = $context['teacher']->user;
    $this->actingAs($teacherUser);

    $date = now()->toDateString();

    app(AttendanceService::class)->saveAttendance(
        scheduleId: $context['schedule']->id,
        date: $date,
        yearId: $context['year']->id,
        userId: (int) $teacherUser->id,
        statuses: [
            (string) $studentA->id => 'A',
            (string) $studentB->id => 'R',
            (string) $studentC->id => 'P',
        ],
    );

    expect(Attendance::query()->where('date', $date)->count())->toBe(2);
    expect(Attendance::query()->where('student_id', $studentC->id)->where('date', $date)->exists())->toBeFalse();
    expect(ClassObservation::query()->count())->toBe(1);

    app(AttendanceService::class)->saveAttendance(
        scheduleId: $context['schedule']->id,
        date: $date,
        yearId: $context['year']->id,
        userId: (int) $teacherUser->id,
        statuses: [
            (string) $studentA->id => 'R',
            (string) $studentB->id => 'R',
            (string) $studentC->id => 'P',
        ],
    );

    expect(Attendance::query()->where('date', $date)->count())->toBe(2);
    expect(Attendance::query()->where('student_id', $studentA->id)->where('date', $date)->value('status'))->toBe('R');
});

it('persiste observaciones por estudiante en saveAttendanceCreate', function (): void {
    $context = webWriteContext();
    [$studentA, $studentB] = $context['students'];
    $teacherUser = $context['teacher']->user;
    $this->actingAs($teacherUser);

    $date = now()->toDateString();

    app(AttendanceService::class)->saveAttendanceCreate(
        scheduleId: $context['schedule']->id,
        date: $date,
        yearId: $context['year']->id,
        userId: (int) $teacherUser->id,
        statuses: [
            (string) $studentA->id => 'A',
            (string) $studentB->id => 'A',
        ],
        observations: [
            $studentA->id => '  Llegó a la segunda hora.  ',
        ],
    );

    expect(Attendance::query()->where('date', $date)->count())->toBe(2);
    expect(Attendance::query()->where('student_id', $studentA->id)->where('date', $date)->value('observation'))->toBe('Llegó a la segunda hora.');
    expect(Attendance::query()->where('student_id', $studentB->id)->where('date', $date)->value('observation'))->toBeNull();

    app(AttendanceService::class)->saveAttendanceCreate(
        scheduleId: $context['schedule']->id,
        date: $date,
        yearId: $context['year']->id,
        userId: (int) $teacherUser->id,
        statuses: [
            (string) $studentA->id => 'A',
            (string) $studentB->id => 'A',
        ],
        observations: [
            $studentA->id => '  Ausente con justificativo.  ',
        ],
    );

    expect(Attendance::query()->where('date', $date)->count())->toBe(2);
    expect(Attendance::query()->where('student_id', $studentA->id)->where('date', $date)->value('observation'))->toBe('Ausente con justificativo.');
});

it('guarda y actualiza calificaciones rápidas desde el servicio web', function (): void {
    $context = webWriteContext();
    [$studentA, $studentB, $studentC] = $context['students'];
    $teacherUser = $context['teacher']->user;

    $activity = Activity::query()->firstOrFail();

    app(QuickGradesService::class)->saveQuickGrades(
        activityId: (int) $activity->id,
        values: [
            (string) $studentA->id => '9.5',
            (string) $studentB->id => '7',
            (string) $studentC->id => '',
        ],
        userId: (int) $teacherUser->id,
    );

    expect(ActivityGrade::query()->where('activity_id', $activity->id)->count())->toBe(3);
    expect(ActivityGrade::query()->where('student_id', $studentA->id)->where('activity_id', $activity->id)->value('grade'))->toBe(9.5);
    expect(ActivityGrade::query()->where('student_id', $studentB->id)->where('activity_id', $activity->id)->value('grade'))->toBe(7.0);
    expect(ActivityGrade::query()->where('student_id', $studentC->id)->where('activity_id', $activity->id)->value('grade'))->toBeNull();

    app(QuickGradesService::class)->saveQuickGrades(
        activityId: (int) $activity->id,
        values: [
            (string) $studentA->id => '9.5',
            (string) $studentB->id => '',
            (string) $studentC->id => '6',
        ],
        userId: (int) $teacherUser->id,
    );

    expect(ActivityGrade::query()->where('activity_id', $activity->id)->count())->toBe(3);
    expect(ActivityGrade::query()->where('student_id', $studentB->id)->where('activity_id', $activity->id)->value('grade'))->toBeNull();
    expect(ActivityGrade::query()->where('student_id', $studentC->id)->where('activity_id', $activity->id)->value('grade'))->toBe(6.0);
});
