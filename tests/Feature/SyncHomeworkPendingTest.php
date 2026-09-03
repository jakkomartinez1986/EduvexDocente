<?php

declare(strict_types=1);

use App\Actions\Academic\SaveGradeAction;
use App\Jobs\SyncHomeworkPendingForActivity;
use App\Models\StudentManagement\Academics\HomeworkPending;

function homeworkPendingContext(): array
{
    $context = syncGradebookContext();

    return $context;
}

it('crea tareas pendientes masivamente para los matriculados sin nota', function (): void {
    $context = homeworkPendingContext();
    $this->actingAs($context['teacher']->user);

    app(SaveGradeAction::class)((int) $context['activity']->id, (int) $context['students'][0]->id, 9);

    $pending = HomeworkPending::where('activity_id', $context['activity']->id)
        ->where('status', 'not_submitted')
        ->pluck('student_id')
        ->toArray();

    $submitted = HomeworkPending::where('activity_id', $context['activity']->id)
        ->where('status', 'submitted')
        ->pluck('student_id')
        ->toArray();

    expect($pending)->toMatchArray([(int) $context['students'][1]->id, (int) $context['students'][2]->id])
        ->and($submitted)->not->toContain((int) $context['students'][0]->id)
        ->and(HomeworkPending::where('activity_id', $context['activity']->id)->count())->toBe(2);
});

it('marca como presentadas las pendientes de quien recibe nota', function (): void {
    $context = homeworkPendingContext();
    $this->actingAs($context['teacher']->user);

    HomeworkPending::create([
        'activity_id' => $context['activity']->id,
        'student_id' => $context['students'][0]->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'teacher_id' => $context['teacher']->id,
        'year_id' => $context['year']->id,
        'description' => 'Tarea no presentada',
        'due_date' => now(),
        'status' => 'not_submitted',
    ]);

    app(SaveGradeAction::class)((int) $context['activity']->id, (int) $context['students'][0]->id, 8.5);

    $row = HomeworkPending::where('activity_id', $context['activity']->id)
        ->where('student_id', $context['students'][0]->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('submitted');
});

it('es idempotente: no duplica filas para los que siguen sin nota', function (): void {
    $context = homeworkPendingContext();

    SyncHomeworkPendingForActivity::dispatch((int) $context['activity']->id);
    SyncHomeworkPendingForActivity::dispatch((int) $context['activity']->id);

    $rows = HomeworkPending::where('activity_id', $context['activity']->id)
        ->where('status', 'not_submitted')
        ->get();

    expect($rows->count())->toBe(3);
});

it('actualiza de presentado a pendiente cuando se borra la nota', function (): void {
    $context = homeworkPendingContext();
    $this->actingAs($context['teacher']->user);

    app(SaveGradeAction::class)((int) $context['activity']->id, (int) $context['students'][0]->id, 7);
    app(SaveGradeAction::class)((int) $context['activity']->id, (int) $context['students'][0]->id, '');

    $pending = HomeworkPending::where('activity_id', $context['activity']->id)
        ->where('status', 'not_submitted')
        ->count();

    // Al borrar la nota, el estudiante vuelve a la lista de pendientes
    // (no debe duplicarse).
    expect($pending)->toBe(3);
});
