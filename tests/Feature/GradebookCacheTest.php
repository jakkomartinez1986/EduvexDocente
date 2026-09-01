<?php

declare(strict_types=1);

use App\Actions\Academic\SaveGradeAction;
use App\Actions\TeacherManagement\SaveQuickGradesAction;
use App\Services\TeacherManagement\GradebookCache;
use Illuminate\Support\Facades\Cache;

it('caches averages for a class/period and serves the cached value', function (): void {
    $cache = app(GradebookCache::class);
    $key = $cache->key(1, 2, 3, 4, 5);

    Cache::flush();
    $calls = 0;

    $first = $cache->averages(1, 2, 3, 4, 5, function () use (&$calls): array {
        $calls++;

        return [100 => 7.5, 200 => 6.0];
    });

    $second = $cache->averages(1, 2, 3, 4, 5, function () use (&$calls): array {
        $calls++;

        return [100 => 7.5, 200 => 6.0];
    });

    expect($first)->toBe([100 => 7.5, 200 => 6.0])
        ->and($second)->toBe([100 => 7.5, 200 => 6.0])
        ->and($calls)->toBe(1)
        ->and(Cache::has($key))->toBeTrue();
});

it('recomputes after the cache is forgotten (invalidation on write)', function (): void {
    $cache = app(GradebookCache::class);
    Cache::flush();

    $cache->averages(1, 2, 3, 4, 5, fn (): array => [100 => 7.5]);
    expect(Cache::has($cache->key(1, 2, 3, 4, 5)))->toBeTrue();

    $cache->forget(1, 2, 3, 4, 5);
    expect(Cache::has($cache->key(1, 2, 3, 4, 5)))->toBeFalse();
});

it('needs lock-safe regeneration when the cache is empty', function (): void {
    $cache = app(GradebookCache::class);
    Cache::flush();

    $result = $cache->averages(9, 8, 7, 6, 5, fn (): array => [1 => 4.0]);

    expect($result)->toBe([1 => 4.0])
        ->and(Cache::has($cache->key(9, 8, 7, 6, 5)))->toBeTrue();
});

it('invalidates the class cache when quick grades are saved', function (): void {
    $context = syncGradebookContext();
    $cache = app(GradebookCache::class);

    $yearId = (int) $context['year']->id;
    $subjectId = (int) $context['subject']->id;
    $gradeId = (int) $context['grade']->id;
    $teacherId = (int) $context['teacher']->id;
    $trimesterId = (int) $context['trimester']->id;

    $cache->averages($yearId, $subjectId, $gradeId, $teacherId, $trimesterId, fn (): array => []);
    expect(Cache::has($cache->key($yearId, $subjectId, $gradeId, $teacherId, $trimesterId)))->toBeTrue();

    [$a, $b] = $context['students'];

    app(SaveQuickGradesAction::class)->handle(
        activityId: $context['activity']->id,
        values: [$a->id => '8.5', $b->id => '7.0'],
        userId: $context['teacher']->user_id,
    );

    expect(Cache::has($cache->key($yearId, $subjectId, $gradeId, $teacherId, $trimesterId)))->toBeFalse();
});

it('invalidates the class cache when a single grade is saved', function (): void {
    $context = syncGradebookContext();
    $cache = app(GradebookCache::class);

    $yearId = (int) $context['year']->id;
    $subjectId = (int) $context['subject']->id;
    $gradeId = (int) $context['grade']->id;
    $teacherId = (int) $context['teacher']->id;
    $trimesterId = (int) $context['trimester']->id;

    $cache->averages($yearId, $subjectId, $gradeId, $teacherId, $trimesterId, fn (): array => []);
    expect(Cache::has($cache->key($yearId, $subjectId, $gradeId, $teacherId, $trimesterId)))->toBeTrue();

    [$a] = $context['students'];

    $this->actingAs($context['teacher']->user);

    app(SaveGradeAction::class)(
        activityId: $context['activity']->id,
        studentId: $a->id,
        value: '9.0',
    );

    expect(Cache::has($cache->key($yearId, $subjectId, $gradeId, $teacherId, $trimesterId)))->toBeFalse();
});
