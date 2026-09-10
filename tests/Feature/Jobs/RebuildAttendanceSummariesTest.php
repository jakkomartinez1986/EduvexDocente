<?php

use App\Jobs\RebuildAttendanceSummaries;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\AttendanceSummary;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Services\AcademicYearService;
use App\Services\Api\V1\TeacherManagement\AttendanceSummariesRebuilder;

function attendanceRebuildContext(): array
{
    $context = academicContext();

    $students = Student::factory()->count(3)->create();
    $students->each(fn (Student $student) => StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]));

    return [...$context, 'students' => $students];
}

it('materializa los conteos de asistencia por estudiante y período', function (): void {
    $context = attendanceRebuildContext();
    [$studentA, $studentB, $studentC] = $context['students'];
    $today = now()->toDateString();

    // Una clase observada en el horario del curso (todos los docentes del grado).
    ClassObservation::factory()->create([
        'class_schedule_id' => $context['schedule']->id,
        'teacher_id' => $context['teacher']->id,
        'year_id' => $context['year']->id,
        'observation_date' => $today,
    ]);

    Attendance::factory()->create([
        'class_schedule_id' => $context['schedule']->id,
        'student_id' => $studentA->id,
        'year_id' => $context['year']->id,
        'date' => $today,
        'status' => 'I',
        'recorded_by' => $context['teacher']->user_id,
    ]);

    Attendance::factory()->create([
        'class_schedule_id' => $context['schedule']->id,
        'student_id' => $studentB->id,
        'year_id' => $context['year']->id,
        'date' => $today,
        'status' => 'A',
        'recorded_by' => $context['teacher']->user_id,
    ]);

    app(AttendanceSummariesRebuilder::class)->rebuild(
        (int) $context['year']->id,
        (int) $context['trimester']->id,
    );

    $summaries = AttendanceSummary::query()
        ->where('year_id', $context['year']->id)
        ->where('trimester_id', $context['trimester']->id)
        ->get()
        ->keyBy('student_id');

    expect($summaries)->toHaveCount(3);

    // total_classes = 1 clase observada; presentes = 1 - explícitos.
    expect($summaries[$studentA->id])
        ->total_classes->toBe(1)
        ->present_count->toBe(0)
        ->unjustified_count->toBe(1);

    expect($summaries[$studentB->id])
        ->late_count->toBe(1)
        ->present_count->toBe(0);

    expect($summaries[$studentC->id])
        ->present_count->toBe(1)
        ->late_count->toBe(0);
});

it('es idempotente sobre la clave natural al ejecutarse de nuevo', function (): void {
    $context = attendanceRebuildContext();
    [$studentA] = $context['students'];
    $today = now()->toDateString();

    ClassObservation::factory()->create([
        'class_schedule_id' => $context['schedule']->id,
        'teacher_id' => $context['teacher']->id,
        'year_id' => $context['year']->id,
        'observation_date' => $today,
    ]);

    Attendance::factory()->create([
        'class_schedule_id' => $context['schedule']->id,
        'student_id' => $studentA->id,
        'year_id' => $context['year']->id,
        'date' => $today,
        'status' => 'J',
        'recorded_by' => $context['teacher']->user_id,
    ]);

    $rebuilder = app(AttendanceSummariesRebuilder::class);

    $rebuilder->rebuild((int) $context['year']->id, (int) $context['trimester']->id);
    $rebuilder->rebuild((int) $context['year']->id, (int) $context['trimester']->id);

    expect(AttendanceSummary::query()->where('trimester_id', $context['trimester']->id)->count())
        ->toBe(3);
});

it('el job resuelve el año activo cuando no recibe year_id', function (): void {
    $context = attendanceRebuildContext();
    [$studentA] = $context['students'];
    $today = now()->toDateString();

    ClassObservation::factory()->create([
        'class_schedule_id' => $context['schedule']->id,
        'teacher_id' => $context['teacher']->id,
        'year_id' => $context['year']->id,
        'observation_date' => $today,
    ]);

    app(RebuildAttendanceSummaries::class)->handle(
        app(AttendanceSummariesRebuilder::class),
        app(AcademicYearService::class),
    );

    expect(AttendanceSummary::query()->where('trimester_id', $context['trimester']->id)->count())
        ->toBe(3);
});

it('el job no hace nada si no hay año activo ni year_id', function (): void {
    ScolarYear::query()->delete();

    app(RebuildAttendanceSummaries::class)->handle(
        app(AttendanceSummariesRebuilder::class),
        app(AcademicYearService::class),
    );

    expect(AttendanceSummary::query()->count())->toBe(0);
});
