<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Identity\Users\Student;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\User;
use App\Support\Database\BulkWrite;

/**
 * Verifica que BulkWrite (caseUpdate/insertBatch) opere correctamente
 * contra el motor activo usando SQL estándar (CASE WHEN) y reintento por
 * fila ante carreras de unicidad.
 */
function bulkWriteContext(): array
{
    $context = academicContext();

    $students = Student::factory()->count(3)->create();
    $user = User::factory()->create();
    $date = now()->toDateString();

    CalendarDay::factory()->create([
        'year_id' => $context['year']->id,
        'date' => $date,
    ]);

    return [...$context, 'students' => $students, 'user' => $user, 'date' => $date];
}

it('caseUpdate asigna valores distintos por fila en una sola sentencia', function (): void {
    $c = bulkWriteContext();

    $attendances = $c['students']->map(fn (Student $student) => Attendance::factory()->create([
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $student->id,
        'date' => $c['date'],
        'status' => 'I',
        'recorded_by' => $c['user']->id,
    ]));

    $statusBy = [];
    $observationBy = [];

    foreach ($c['students'] as $index => $student) {
        $statusBy[$student->id] = ['A', 'J', 'AI'][$index];
        $observationBy[$student->id] = 'Obser '.($index + 1);
    }

    BulkWrite::caseUpdate(
        Attendance::query()
            ->where('class_schedule_id', $c['schedule']->id)
            ->whereDate('date', $c['date']),
        'student_id',
        [
            'status' => $statusBy,
            'observation' => $observationBy,
        ],
        ['recorded_by' => $c['user']->id],
    );

    foreach ($c['students'] as $index => $student) {
        $attendance = $attendances[$index]->fresh();

        expect($attendance->status)->toBe(['A', 'J', 'AI'][$index])
            ->and($attendance->observation)->toBe('Obser '.($index + 1))
            ->and($attendance->recorded_by)->toBe($c['user']->id);
    }
});

it('caseUpdate respeta NULL y números dentro de la expresión CASE', function (): void {
    $c = bulkWriteContext();

    foreach ($c['students']->take(2) as $student) {
        Attendance::factory()->create([
            'class_schedule_id' => $c['schedule']->id,
            'student_id' => $student->id,
            'date' => $c['date'],
            'status' => 'I',
            'recorded_by' => $c['user']->id,
        ]);
    }

    BulkWrite::caseUpdate(
        Attendance::query()
            ->where('class_schedule_id', $c['schedule']->id)
            ->whereDate('date', $c['date']),
        'student_id',
        [
            'status' => [
                $c['students'][0]->id => 'A',
                $c['students'][1]->id => 'J',
            ],
            'observation' => [
                $c['students'][0]->id => null,
                $c['students'][1]->id => 'Con justificativo',
            ],
        ],
    );

    expect(Attendance::where('student_id', $c['students'][0]->id)->value('observation'))->toBeNull()
        ->and(Attendance::where('student_id', $c['students'][1]->id)->value('observation'))->toBe('Con justificativo');
});

it('insertBatch inserta el lote completo', function (): void {
    $c = bulkWriteContext();

    $rows = $c['students']->map(fn (Student $student) => [
        'class_schedule_id' => $c['schedule']->id,
        'student_id' => $student->id,
        'date' => $c['date'],
        'status' => 'I',
        'recorded_by' => $c['user']->id,
        'created_at' => now(),
        'updated_at' => now(),
    ])->all();

    BulkWrite::insertBatch(Attendance::class, $rows, function (array $row): void {
        throw new RuntimeException('No debería haber conflictos.');
    });

    expect(Attendance::query()->count())->toBe(3);
});

it('insertBatch delega en onConflict cuando la fila ya existe', function (): void {
    $gradebookContext = syncGradebookContext();
    $activity = Activity::query()->firstOrFail();

    $student = $gradebookContext['students'][0];
    $user = $gradebookContext['teacher']->user;

    ActivityGrade::factory()->create([
        'activity_id' => $activity->id,
        'student_id' => $student->id,
        'grade' => 7.0,
        'recorded_by' => $user->id,
    ]);

    $resolved = false;

    BulkWrite::insertBatch(
        ActivityGrade::class,
        [[
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'grade' => 9.0,
            'recorded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]],
        function (array $row) use (&$resolved): void {
            $resolved = true;
        },
    );

    expect($resolved)->toBeTrue();
});
