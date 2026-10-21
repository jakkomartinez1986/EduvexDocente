<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Services\TeacherManagement\QuickGradesService;
use Livewire\Livewire;

/**
 * El menú rápido de "Registrar Calificaciones" debe listar TODOS los bloques
 * de evaluación del aula (no solo el primero) y permitir cambiar de bloque y
 * de actividad.
 */
function quickGradesContext(): array
{
    $context = academicContext();

    $blockOne = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'name' => 'Bloque 1',
        'order' => 1,
    ]);

    $blockTwo = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'name' => 'Bloque 2',
        'order' => 2,
    ]);

    $activityOne = Activity::factory()->create([
        'assessment_block_id' => $blockOne->id,
        'name' => 'Actividad del bloque 1',
    ]);

    $activityTwo = Activity::factory()->create([
        'assessment_block_id' => $blockTwo->id,
        'name' => 'Actividad del bloque 2',
    ]);

    return [...$context, 'blockOne' => $blockOne, 'blockTwo' => $blockTwo, 'activityOne' => $activityOne, 'activityTwo' => $activityTwo];
}

it('carga todos los bloques de evaluación con sus actividades', function (): void {
    $context = quickGradesContext();

    $blocks = app(QuickGradesService::class)->loadBlocks($context['schedule']->id, $context['year']->id);

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['name'])->toBe('Bloque 1')
        ->and($blocks[0]['activities'][0]['name'])->toBe('Actividad del bloque 1')
        ->and($blocks[1]['name'])->toBe('Bloque 2')
        ->and($blocks[1]['activities'][0]['name'])->toBe('Actividad del bloque 2');
});

it('expone la fecha de la actividad como texto comparable', function (): void {
    $context = quickGradesContext();

    $blocks = app(QuickGradesService::class)->loadBlocks($context['schedule']->id, $context['year']->id);

    expect($blocks[0]['activities'][0]['date'])->toBe(now()->toDateString());
});

it('selecciona el bloque por defecto como el primero con actividades', function (): void {
    $context = quickGradesContext();

    $blocks = app(QuickGradesService::class)->loadBlocks($context['schedule']->id, $context['year']->id);

    expect(app(QuickGradesService::class)->getDefaultBlock($blocks)['id'])->toBe($context['blockOne']->id)
        ->and(app(QuickGradesService::class)->getDefaultActivity($blocks[0]['activities'])['id'])
        ->toBe($context['activityOne']->id);
});

it('el modal rápido lista todos los bloques y permite cambiar de bloque', function (): void {
    $context = quickGradesContext();

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.schedules.timeline')
        ->call('openQuickGradesModal', $context['schedule']->id)
        ->assertSet('showQuickGradesModal', true)
        ->assertSet('quickGradesSelectedBlockId', $context['blockOne']->id)
        ->assertSet('quickGradesActivityId', $context['activityOne']->id)
        ->assertSet('quickGradesBlocks', fn (array $blocks): bool => count($blocks) === 2)
        ->assertSee('Bloque 1')
        ->assertSee('Bloque 2')
        ->assertSee('selectQuickGradesBlock')
        ->call('selectQuickGradesBlock', $context['blockTwo']->id)
        ->assertSet('quickGradesSelectedBlockId', $context['blockTwo']->id)
        ->assertSet('quickGradesBlockName', 'Bloque 2')
        ->assertSet('quickGradesActivityId', $context['activityTwo']->id)
        ->assertSet('quickGradesActivityName', 'Actividad del bloque 2')
        ->assertSee('Actividad del bloque 2');
});

it('el modal rápido permite elegir una actividad concreta del bloque seleccionado', function (): void {
    $context = quickGradesContext();

    $secondActivity = Activity::factory()->create([
        'assessment_block_id' => $context['blockTwo']->id,
        'name' => 'Segunda actividad del bloque 2',
    ]);

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.schedules.timeline')
        ->call('openQuickGradesModal', $context['schedule']->id)
        ->call('selectQuickGradesBlock', $context['blockTwo']->id)
        ->call('selectQuickGradesActivity', $secondActivity->id)
        ->assertSet('quickGradesActivityId', $secondActivity->id)
        ->assertSet('quickGradesActivityName', 'Segunda actividad del bloque 2');
});

it('guarda calificaciones sobre una actividad de un bloque distinto al primero', function (): void {
    $context = quickGradesContext();

    $student = Student::factory()->create();
    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]);

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.schedules.timeline')
        ->call('openQuickGradesModal', $context['schedule']->id)
        ->call('selectQuickGradesBlock', $context['blockTwo']->id)
        ->set('quickGradesValues', [$student->id => '8.5'])
        ->call('saveQuickGrades')
        ->assertSet('showQuickGradesModal', false);

    expect(
        ActivityGrade::query()
            ->where('activity_id', $context['activityTwo']->id)
            ->where('student_id', $student->id)
            ->value('grade')
    )->toBe(8.5);
});
