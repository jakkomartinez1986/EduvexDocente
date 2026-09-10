<?php

declare(strict_types=1);

use App\Jobs\RecalculateCourseAverages;
use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Services\TeacherManagement\GradebookCache;
use Illuminate\Support\Facades\Cache;

function recalcCourseContext(): array
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

    [$activityA, $activityB] = Activity::factory()->count(2)->create([
        'assessment_block_id' => $block->id,
        'max_score' => 10,
    ]);

    ActivityGrade::factory()->create([
        'activity_id' => $activityA->id,
        'student_id' => $students[0]->id,
        'grade' => 8.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);
    ActivityGrade::factory()->create([
        'activity_id' => $activityB->id,
        'student_id' => $students[0]->id,
        'grade' => 6.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    return [...$context, 'students' => $students, 'block' => $block];
}

it('recálcula y calienta los agregados de la clase en la caché', function (): void {
    Cache::flush();
    $context = recalcCourseContext();
    $student = $context['students'][0];

    RecalculateCourseAverages::dispatchSync(
        $context['year']->id,
        $context['subject']->id,
        $context['grade']->id,
        $context['teacher']->id,
        $context['trimester']->id,
    );

    $cache = app(GradebookCache::class);
    $key = $cache->key(
        $context['year']->id,
        $context['subject']->id,
        $context['grade']->id,
        $context['teacher']->id,
        $context['trimester']->id,
    );

    expect(Cache::has($key))->toBeTrue();

    $calls = 0;
    $aggregates = $cache->averages(
        $context['year']->id,
        $context['subject']->id,
        $context['grade']->id,
        $context['teacher']->id,
        $context['trimester']->id,
        function () use (&$calls): array {
            $calls++;

            return ['formative' => [], 'total' => [], 'hasData' => false];
        },
    );

    // La segunda lectura se sirve de la caché sin recomputar.
    expect($calls)->toBe(0);

    // Bloque 1 con dos actividades: (8 + 6) / 2 = 7.0 (floor).
    expect($aggregates['formative'][$student->id])->toBe(7.0);
});

it('es idempotente: ejecutarlo varias veces produce el mismo promedio', function (): void {
    Cache::flush();
    $context = recalcCourseContext();
    $student = $context['students'][0];
    $cache = app(GradebookCache::class);

    $run = function () use ($context, $cache, $student): ?float {
        RecalculateCourseAverages::dispatchSync(
            $context['year']->id,
            $context['subject']->id,
            $context['grade']->id,
            $context['teacher']->id,
            $context['trimester']->id,
        );

        return $cache->averages(
            $context['year']->id,
            $context['subject']->id,
            $context['grade']->id,
            $context['teacher']->id,
            $context['trimester']->id,
            fn (): array => ['formative' => [], 'total' => [], 'hasData' => false],
        )['formative'][$student->id] ?? null;
    };

    expect($run())->toBe(7.0)
        ->and($run())->toBe(7.0);
});
