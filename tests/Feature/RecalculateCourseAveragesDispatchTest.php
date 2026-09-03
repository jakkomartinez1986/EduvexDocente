<?php

declare(strict_types=1);

use App\Actions\Academic\SaveGradeAction;
use App\Actions\TeacherManagement\SaveQuickGradesAction;
use App\Jobs\RecalculateCourseAverages;
use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Services\TeacherManagement\GradebookCache;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

function recalcDispatchContext(): array
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

    return [...$context, 'students' => $students, 'block' => $block, 'activity' => $activity];
}

function recalcCacheKey(array $context): string
{
    return app(GradebookCache::class)->key(
        $context['year']->id,
        $context['subject']->id,
        $context['grade']->id,
        $context['teacher']->id,
        $context['trimester']->id,
    );
}

it('el comando gradebook:recalculate despacha y calienta las clases del año activo', function (): void {
    Cache::flush();
    $context = recalcDispatchContext();

    $this->artisan('gradebook:recalculate')
        ->expectsOutputToContain('Despachados')
        ->assertExitCode(0);

    expect(Cache::has(recalcCacheKey($context)))->toBeTrue();
});

it('guarda nota y despacha el recálculo de la clase (SaveGradeAction)', function (): void {
    Cache::flush();
    $context = recalcDispatchContext();
    $this->actingAs($context['teacher']->user);

    app(SaveGradeAction::class)((int) $context['activity']->id, (int) $context['students'][0]->id, 9);

    expect(Cache::has(recalcCacheKey($context)))->toBeTrue();
});

it('guarda notas en lote y despacha el recálculo de la clase (SaveQuickGradesAction)', function (): void {
    Cache::flush();
    $context = recalcDispatchContext();
    $this->actingAs($context['teacher']->user);

    app(SaveQuickGradesAction::class)->handle(
        (int) $context['activity']->id,
        [(int) $context['students'][0]->id => '9', (int) $context['students'][1]->id => '7'],
        (int) $context['teacher']->user->id,
    );

    expect(Cache::has(recalcCacheKey($context)))->toBeTrue();
});

it('no despacha nada si no hay año activo', function (): void {
    Bus::fake();
    Cache::flush();

    $this->artisan('gradebook:recalculate')
        ->expectsOutputToContain('No hay año lectivo activo')
        ->assertExitCode(0);

    Bus::assertNotDispatched(RecalculateCourseAverages::class);
});
