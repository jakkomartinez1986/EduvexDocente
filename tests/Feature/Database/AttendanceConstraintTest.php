<?php

use App\Models\Identity\Users\Student;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Semántica obligatoria del índice único de attendances:
 * - Una única asistencia ACTIVA por (class_schedule_id, student_id, date).
 * - Múltiples tombstones / soft deleted permitidos.
 * - Eliminar una activa y crearla de nuevo: permitido.
 */
function attendanceConstraintContext(): array
{
    $context = academicContext();

    $student = Student::factory()->create();
    $user = User::factory()->create();

    $date = now()->toDateString();

    CalendarDay::factory()->create([
        'year_id' => $context['year']->id,
        'date' => $date,
    ]);

    return [...$context, 'student' => $student, 'user' => $user, 'date' => $date];
}

it('Caso 1: no permite dos asistencias activas idénticas', function (): void {
    $c = attendanceConstraintContext();

    Attendance::factory()->create([
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $c['student']->id,
        'date' => $c['date'],
        'status' => 'I',
        'recorded_by' => $c['user']->id,
    ]);

    expect(fn () => Attendance::factory()->create([
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $c['student']->id,
        'date' => $c['date'],
        'status' => 'A',
        'recorded_by' => $c['user']->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('Caso 2: permite una activa y una soft-deleted simultáneas', function (): void {
    $c = attendanceConstraintContext();

    $active = Attendance::factory()->create([
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $c['student']->id,
        'date' => $c['date'],
        'status' => 'I',
        'recorded_by' => $c['user']->id,
    ]);

    // La fila original se convierte en tombstone al soft-deletear...
    $active->delete();

    // ...y puede crearse una nueva activa.
    $replacement = Attendance::factory()->create([
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $c['student']->id,
        'date' => $c['date'],
        'status' => 'A',
        'recorded_by' => $c['user']->id,
    ]);

    expect(Attendance::query()->withTrashed()->count())->toBe(2)
        ->and(Attendance::query()->whereNull('deleted_at')->count())->toBe(1);

    // La activa (replacement) es única en el índice parcial.
    expect(fn () => Attendance::factory()->create([
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $c['student']->id,
        'date' => $c['date'],
        'status' => 'J',
        'recorded_by' => $c['user']->id,
    ]))->toThrow(UniqueConstraintViolationException::class);

    unset($replacement);
});

it('Caso 3: permite múltiples tombstones soft-deleted', function (): void {
    $c = attendanceConstraintContext();

    $first = Attendance::factory()->create([
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $c['student']->id,
        'date' => $c['date'],
        'status' => 'I',
        'recorded_by' => $c['user']->id,
    ]);
    $first->delete();

    $second = Attendance::factory()->create([
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $c['student']->id,
        'date' => $c['date'],
        'status' => 'A',
        'recorded_by' => $c['user']->id,
    ]);
    $second->delete();

    expect(Attendance::query()->withTrashed()->count())->toBe(2)
        ->and(Attendance::query()->onlyTrashed()->count())->toBe(2);
});

it('Caso 4: eliminar la activa y crearla de nuevo está permitido', function (): void {
    $c = attendanceConstraintContext();

    $first = Attendance::factory()->create([
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $c['student']->id,
        'date' => $c['date'],
        'status' => 'I',
        'recorded_by' => $c['user']->id,
    ]);

    $first->delete();

    $recreated = Attendance::factory()->create([
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $c['student']->id,
        'date' => $c['date'],
        'status' => 'A',
        'recorded_by' => $c['user']->id,
    ]);

    expect(Attendance::query()->whereNull('deleted_at')->count())->toBe(1);

    unset($recreated);
});
