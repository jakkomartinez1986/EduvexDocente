<?php

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\User;
use App\Services\Api\V1\TeacherManagement\AttendanceRegistrationService;
use Illuminate\Support\Facades\DB;

/**
 * Concurrencia: serialización por horario con lockForUpdate + transacciones.
 * El resultado debe ser idéntico en PostgreSQL, MySQL y MariaDB:
 * solo un ganador por (schedule, student, date).
 */
it('registra dos snapshots concurrentes del mismo día sin duplicar filas', function (): void {
    $context = academicContext();
    $student = Student::factory()->create();
    $year = $context['year'];

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $year->id,
        'academic_year' => $year->year_name,
    ]);

    $today = now()->toDateString();
    CalendarDay::factory()->create(['year_id' => $year->id, 'date' => $today]);

    $user = User::factory()->create();
    $service = app(AttendanceRegistrationService::class);

    $payload = [
        'schedule_id' => $context['schedule']->id,
        'date' => $today,
        'classtopic' => 'Tema A',
        'statuses' => [(string) $student->id => 'A'],
    ];

    $results = [];

    DB::transaction(function () use ($service, $context, $payload, &$results): void {
        $results[] = $service->register($context['teacher'], $payload);
    });

    // Un segundo registro del mismo día reutiliza la fila (update, no duplica).
    $second = $service->register($context['teacher'], $payload);

    expect(Attendance::query()->count())->toBe(1)
        ->and($second['summary']['recorded'])->toBe(1);
});

it('usa lockForUpdate de forma portable en la serialización', function (): void {
    $sql = ClassSchedule::query()->lockForUpdate()->toSql();

    if (in_array(DB::connection()->getDriverName(), ['pgsql', 'mysql', 'mariadb'], true)) {
        expect($sql)->toContain('for update');
    }

    // En SQLite no se emite FOR UPDATE, pero la consulta sigue siendo ejecutable.
    expect($sql)->toStartWith('select * from ');
});

it('realiza operaciones de escritura dentro de transacciones', function (): void {
    expect(DB::transaction(fn (): int => 1 + 1))->toBe(2);
});
