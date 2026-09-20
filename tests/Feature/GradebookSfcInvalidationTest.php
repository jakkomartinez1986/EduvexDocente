<?php

declare(strict_types=1);

use App\Jobs\RecalculateCourseAverages;
use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Services\TeacherManagement\CourseAveragesComputer;
use App\Services\TeacherManagement\GradebookCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/**
 * Contexto del SFC del libro de calificaciones: docente con horario, período
 * con ventana abierta, dos estudiantes matriculados y un bloque con una
 * actividad (con nota opcional del primer estudiante).
 *
 * @return array<string, mixed>
 */
function gradebookSfcContext(bool $withGrade = false): array
{
    $context = academicContext();

    $students = Student::factory()->count(2)->create();
    $students->each(fn (Student $student) => StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]));

    $block = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'name' => 'Bloque 1',
    ]);

    $activity = Activity::factory()->create([
        'assessment_block_id' => $block->id,
        'max_score' => 10,
    ]);

    if ($withGrade) {
        ActivityGrade::create([
            'activity_id' => $activity->id,
            'student_id' => $students[0]->id,
            'grade' => 9.0,
            'recorded_by' => $context['teacher']->user->id,
        ]);
    }

    return [...$context, 'students' => $students, 'block' => $block, 'activity' => $activity];
}

function gradebookSfcKey(array $context): string
{
    return app(GradebookCache::class)->key(
        $context['year']->id,
        $context['subject']->id,
        $context['grade']->id,
        $context['teacher']->id,
        $context['trimester']->id,
    );
}

function staleAggregates(int $studentId): array
{
    return ['formative' => [$studentId => 5.5], 'total' => [$studentId => 4.4], 'hasData' => true];
}

/**
 * La validación del SFC exige fechas en días laborables; hoy (fin de semana)
 * rompería el test, así que la fecha de actividad se ancla al próximo lunes.
 */
function nextWeekdayDate(): string
{
    $date = Carbon::today();

    while ($date->isWeekend()) {
        $date = $date->addDay();
    }

    return $date->toDateString();
}

function assertRecalcDispatchedFor(array $context): void
{
    Bus::assertDispatched(RecalculateCourseAverages::class, fn (RecalculateCourseAverages $job) => $job->yearId === $context['year']->id
        && $job->subjectId === $context['subject']->id
        && $job->gradeId === $context['grade']->id
        && $job->teacherId === $context['teacher']->id
        && $job->trimesterId === $context['trimester']->id);
}

/**
 * Ejecuta el job real (como lo haría el worker) para verificar que, tras la
 * invalidación de la escritura, recalienta la clase con datos frescos.
 */
function rearmRecalc(array $context): void
{
    (new RecalculateCourseAverages(
        $context['year']->id,
        $context['subject']->id,
        $context['grade']->id,
        $context['teacher']->id,
        $context['trimester']->id,
    ))->handle(app(CourseAveragesComputer::class), app(GradebookCache::class));
}

it('saveGrade invalida los agregados de la clase y despacha el recálculo', function (): void {
    Bus::fake();
    Cache::flush();
    $context = gradebookSfcContext();
    $key = gradebookSfcKey($context);
    $studentId = (int) $context['students'][0]->id;

    Cache::put($key, staleAggregates($studentId), now()->addMinutes(5));

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.gradebook.index')
        ->assertOk()
        ->call('saveGrade', $context['activity']->id, $studentId, '9');

    expect(Cache::get($key))->toBeNull();
    assertRecalcDispatchedFor($context);

    rearmRecalc($context);

    $aggregates = Cache::get($key);
    expect($aggregates['total'][$studentId])->toBe(7.2);
});

it('saveExamGrade invalida los agregados de la clase y despacha el recálculo', function (): void {
    Bus::fake();
    Cache::flush();
    $context = gradebookSfcContext();
    $key = gradebookSfcKey($context);
    $studentId = (int) $context['students'][0]->id;

    Cache::put($key, staleAggregates($studentId), now()->addMinutes(5));

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.gradebook.index')
        ->assertOk()
        ->call('saveExamGrade', $studentId, '8');

    expect(Cache::get($key))->toBeNull();
    assertRecalcDispatchedFor($context);

    rearmRecalc($context);

    $aggregates = Cache::get($key);
    expect($aggregates['total'][$studentId])->toBe(1.12);
});

it('saveProjectGrade invalida los agregados de la clase y despacha el recálculo', function (): void {
    Bus::fake();
    Cache::flush();
    $context = gradebookSfcContext();
    $key = gradebookSfcKey($context);
    $studentId = (int) $context['students'][0]->id;

    Cache::put($key, staleAggregates($studentId), now()->addMinutes(5));

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.gradebook.index')
        ->assertOk()
        ->call('saveProjectGrade', $studentId, '5');

    expect(Cache::get($key))->toBeNull();
    assertRecalcDispatchedFor($context);

    rearmRecalc($context);

    $aggregates = Cache::get($key);
    expect($aggregates['total'][$studentId])->toBe(0.3);
});

it('saveActivity invalida los agregados de la clase y despacha el recálculo', function (): void {
    Bus::fake();
    Cache::flush();
    $context = gradebookSfcContext(withGrade: true);
    $key = gradebookSfcKey($context);
    $studentId = (int) $context['students'][0]->id;

    Cache::put($key, staleAggregates($studentId), now()->addMinutes(5));

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.gradebook.index')
        ->assertOk()
        ->set('activityBlockId', (int) $context['block']->id)
        ->set('activityForm', [
            'name' => 'Actividad 2',
            'topic' => 'Tema 2',
            'description' => '',
            'date' => nextWeekdayDate(),
            'max_score' => 10,
        ])
        ->call('saveActivity');

    expect(Cache::get($key))->toBeNull();
    assertRecalcDispatchedFor($context);

    rearmRecalc($context);

    $aggregates = Cache::get($key);
    expect($aggregates['formative'][$studentId])->toBe(4.5);
});

it('saveBlock invalida los agregados de la clase y despacha el recálculo', function (): void {
    Bus::fake();
    Cache::flush();
    $context = gradebookSfcContext();
    $key = gradebookSfcKey($context);
    $studentId = (int) $context['students'][0]->id;

    Cache::put($key, staleAggregates($studentId), now()->addMinutes(5));

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.gradebook.index')
        ->assertOk()
        ->set('editingBlockId', (int) $context['block']->id)
        ->set('blockForm', ['name' => 'Bloque renovado', 'description' => '', 'internal_percentage' => null, 'order' => 1])
        ->call('saveBlock');

    expect(Cache::get($key))->toBeNull();
    assertRecalcDispatchedFor($context);

    rearmRecalc($context);

    expect(Cache::has($key))->toBeTrue();
});

it('deleteActivity invalida los agregados y el recálculo los computa sin la actividad', function (): void {
    Bus::fake();
    Cache::flush();
    $context = gradebookSfcContext(withGrade: true);
    $key = gradebookSfcKey($context);
    $studentId = (int) $context['students'][0]->id;

    Cache::put($key, staleAggregates($studentId), now()->addMinutes(5));

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.gradebook.index')
        ->assertOk()
        ->call('deleteActivity', $context['activity']->id);

    expect(Cache::get($key))->toBeNull();
    assertRecalcDispatchedFor($context);

    rearmRecalc($context);

    $aggregates = Cache::get($key);
    expect($aggregates['formative'])->toBe([])
        ->and($aggregates['total'][$studentId])->toBeNull();
});

it('deleteBlock invalida los agregados y el recálculo los computa sin el bloque', function (): void {
    Bus::fake();
    Cache::flush();
    $context = gradebookSfcContext(withGrade: true);
    $key = gradebookSfcKey($context);
    $studentId = (int) $context['students'][0]->id;

    Cache::put($key, staleAggregates($studentId), now()->addMinutes(5));

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.gradebook.index')
        ->assertOk()
        ->call('deleteBlock', $context['block']->id);

    expect(Cache::get($key))->toBeNull();
    assertRecalcDispatchedFor($context);

    rearmRecalc($context);

    $aggregates = Cache::get($key);
    expect($aggregates['hasData'])->toBeFalse()
        ->and($aggregates['formative'])->toBe([]);
});
