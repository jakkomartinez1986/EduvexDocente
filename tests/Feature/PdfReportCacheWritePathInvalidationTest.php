<?php

declare(strict_types=1);

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Services\Academic\PdfReportCache;
use App\Services\Api\V1\TeacherManagement\AttendanceRegistrationService;
use App\Services\TeacherManagement\AttendanceService;
use Illuminate\Support\Facades\Cache;

/**
 * Las rutas de asistencia escriben en lote con BulkWrite (sin eventos
 * Eloquent), por lo que la invalidación de reportes PDF debe ser explícita.
 * Estos tests verifican que ninguna quede sin invalidar.
 */

/**
 * @return array<string, mixed>
 */
function pdfWritePathContext(): array
{
    $context = academicContext();

    $students = Student::factory()->count(2)->create();
    $students->each(fn (Student $student) => StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]));

    return [...$context, 'students' => $students];
}

/**
 * @return array{subject: int, teacher: int, student: int}
 */
function pdfWritePathVersions(int $subjectId, int $gradeId, int $teacherId, int $studentId): array
{
    $cache = app(PdfReportCache::class);

    return [
        'subject' => (int) $cache->version("subject-grade:{$subjectId}:{$gradeId}"),
        'teacher' => (int) $cache->version("teacher:{$teacherId}"),
        'student' => (int) $cache->version("student:{$studentId}"),
    ];
}

it('invalida los reportes al guardar asistencia desde el horario del docente', function (): void {
    $context = pdfWritePathContext();
    [$student] = $context['students'];
    $schedule = $context['schedule'];
    $teacherId = (int) $schedule->teacher_id;

    $this->actingAs($context['teacher']->user);

    Cache::flush();

    $before = pdfWritePathVersions((int) $schedule->subject_id, (int) $schedule->grade_id, $teacherId, (int) $student->id);

    app(AttendanceService::class)->saveAttendance(
        scheduleId: (int) $schedule->id,
        date: now()->toDateString(),
        yearId: (int) $context['year']->id,
        userId: (int) $context['teacher']->user_id,
        statuses: [(string) $student->id => 'A'],
        classtopic: 'Ecuaciones',
        observation: 'Participación activa',
    );

    $after = pdfWritePathVersions((int) $schedule->subject_id, (int) $schedule->grade_id, $teacherId, (int) $student->id);

    expect($after['subject'])->toBeGreaterThan($before['subject'])
        ->and($after['teacher'])->toBeGreaterThan($before['teacher'])
        ->and($after['student'])->toBeGreaterThan($before['student']);
});

it('invalida los reportes al registrar asistencia desde la página de crear', function (): void {
    $context = pdfWritePathContext();
    [$student] = $context['students'];
    $schedule = $context['schedule'];
    $teacherId = (int) $schedule->teacher_id;

    Cache::flush();

    $before = pdfWritePathVersions((int) $schedule->subject_id, (int) $schedule->grade_id, $teacherId, (int) $student->id);

    app(AttendanceService::class)->saveAttendanceCreate(
        scheduleId: (int) $schedule->id,
        date: now()->toDateString(),
        yearId: (int) $context['year']->id,
        userId: (int) $context['teacher']->user_id,
        statuses: [(string) $student->id => 'I'],
    );

    $after = pdfWritePathVersions((int) $schedule->subject_id, (int) $schedule->grade_id, $teacherId, (int) $student->id);

    expect($after['subject'])->toBeGreaterThan($before['subject'])
        ->and($after['teacher'])->toBeGreaterThan($before['teacher'])
        ->and($after['student'])->toBeGreaterThan($before['student']);
});

it('invalida los reportes al registrar asistencia por API/sync', function (): void {
    $context = pdfWritePathContext();
    [$student] = $context['students'];
    $schedule = $context['schedule'];
    $teacherId = (int) $schedule->teacher_id;

    Cache::flush();

    $before = pdfWritePathVersions((int) $schedule->subject_id, (int) $schedule->grade_id, $teacherId, (int) $student->id);

    app(AttendanceRegistrationService::class)->register($context['teacher'], [
        'schedule_id' => $schedule->id,
        'date' => now()->toDateString(),
        'classtopic' => 'Tema en lote',
        'statuses' => [(string) $student->id => 'I'],
    ]);

    $after = pdfWritePathVersions((int) $schedule->subject_id, (int) $schedule->grade_id, $teacherId, (int) $student->id);

    expect($after['subject'])->toBeGreaterThan($before['subject'])
        ->and($after['teacher'])->toBeGreaterThan($before['teacher'])
        ->and($after['student'])->toBeGreaterThan($before['student']);
});
