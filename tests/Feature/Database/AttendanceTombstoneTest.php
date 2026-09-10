<?php

use App\Models\Identity\Users\Student;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\Sync\SyncTombstone;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\User;

/**
 * Valida la integración observer → tombstone que el sync consume.
 */
function attendanceTombstoneContext(): array
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

it('publica un tombstone al soft-deletear una asistencia', function (): void {
    $c = attendanceTombstoneContext();

    $attendance = Attendance::factory()->create([
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $c['student']->id,
        'date' => $c['date'],
        'status' => 'I',
        'recorded_by' => $c['user']->id,
    ]);

    $attendance->delete();

    expect(Attendance::query()->whereNull('deleted_at')->count())->toBe(0);
    expect(Attendance::query()->withTrashed()->count())->toBe(1);

    $tombstone = SyncTombstone::query()
        ->where('entity', 'attendance')
        ->where('entity_id', $attendance->id)
        ->first();

    expect($tombstone)->not->toBeNull()
        ->and($tombstone->owner_user_id)->toBe($c['user']->id);
});

it('re-cargar el mismo horario tras soft delete no publica tombstones dobles', function (): void {
    $c = attendanceTombstoneContext();

    $attendance = Attendance::factory()->create([
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $c['student']->id,
        'date' => $c['date'],
        'status' => 'I',
        'recorded_by' => $c['user']->id,
    ]);

    $attendance->delete();
    $attendance->delete();

    expect(SyncTombstone::query()
        ->where('entity', 'attendance')
        ->where('entity_id', $attendance->id)
        ->count())->toBe(1);
});
