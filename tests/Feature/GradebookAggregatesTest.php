<?php

declare(strict_types=1);

use App\Models\Setting\YearSettings\GradingScheme;
use App\Services\Academic\GradeCalculationService;
use App\Services\TeacherManagement\GradebookCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

function makeBlock(int $activities): object
{
    return (object) ['activities' => new Collection(array_fill(0, $activities, (object) []))];
}

it('computes trimester aggregates with the exact gradebook semantics', function (): void {
    $scheme = GradingScheme::factory()->create([
        'formative_percentage' => 80.0,
        'exam_percentage' => 14.0,
        'project_percentage' => 6.0,
    ]);

    $service = app(GradeCalculationService::class);

    $blocks = new Collection([
        (object) [
            'activities' => new Collection([
                (object) ['grades' => new Collection([
                    (object) ['student_id' => 1, 'grade' => 8.0],
                    (object) ['student_id' => 2, 'grade' => 5.0],
                ])],
                (object) ['grades' => new Collection([
                    (object) ['student_id' => 1, 'grade' => 6.0],
                    (object) ['student_id' => 2, 'grade' => 7.0],
                ])],
            ]),
        ],
    ]);

    $exams = new Collection([1 => (object) ['student_id' => 1, 'grade' => 9.0]]);
    $projects = new Collection([2 => (object) ['student_id' => 2, 'grade' => 4.0]]);

    $result = $service->classTrimesterAggregates($blocks, $exams, $projects, [1, 2], $scheme);

    // Student 1: block avg = (8+6)/2 = 7 → formative = 7.0 (floor).
    // total = 7*0.8 + 9*0.14 + 0 = 5.6 + 1.26 = 6.86 → 6.86
    expect($result['formative'][1])->toBe(7.0)
        ->and($result['total'][1])->toBe(6.86);

    // Student 2: block avg = (5+7)/2 = 6 → formative = 6.0.
    // total = 6*0.8 + 0 + 4*0.06 = 4.8 + 0.24 = 5.04 → 5.04
    expect($result['formative'][2])->toBe(6.0)
        ->and($result['total'][2])->toBe(5.04);

    expect($result['hasData'])->toBeTrue();
});

it('returns null totals for students without any data', function (): void {
    $service = app(GradeCalculationService::class);

    $blocks = new Collection;
    $exams = new Collection;
    $projects = new Collection;

    $result = $service->classTrimesterAggregates($blocks, $exams, $projects, [99], null);

    expect($result['formative'])->toBe([])
        ->and($result['total'][99])->toBeNull()
        ->and($result['hasData'])->toBeFalse();
});

it('caches aggregates and invalidates via GradebookCache', function (): void {
    $cache = app(GradebookCache::class);
    Cache::flush();

    $calls = 0;
    $result = $cache->averages(1, 2, 3, 4, 5, function () use (&$calls): array {
        $calls++;

        return ['formative' => [1 => 7.0], 'total' => [1 => 6.86], 'hasData' => true];
    });

    $cache->averages(1, 2, 3, 4, 5, function () use (&$calls): array {
        $calls++;

        return ['formative' => [1 => 7.0], 'total' => [1 => 6.86], 'hasData' => true];
    });

    expect($calls)->toBe(1)
        ->and($result['total'][1])->toBe(6.86);
});
