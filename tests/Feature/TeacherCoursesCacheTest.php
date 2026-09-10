<?php

declare(strict_types=1);

use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\TeacherManagement\TeacherCoursesCache;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('caches teacher courses and serves the cached value', function (): void {
    $cache = app(TeacherCoursesCache::class);
    $calls = 0;

    $compute = function () use (&$calls): array {
        $calls++;

        return [['subject_id' => 1, 'grade_id' => 2, 'subject_name' => 'Matemática']];
    };

    $first = $cache->courses(1, 2026, $compute);
    $second = $cache->courses(1, 2026, $compute);

    expect($first)->toBe($second)
        ->and($calls)->toBe(1)
        ->and(Cache::has($cache->key(1, 2026)))->toBeTrue();
});

it('caches a different slot per teacher and year', function (): void {
    $cache = app(TeacherCoursesCache::class);
    [$keyA, $keyB, $keyC] = [$cache->key(1, 2026), $cache->key(2, 2026), $cache->key(1, 2027)];

    expect($keyA)->not->toBe($keyB)
        ->and($keyA)->not->toBe($keyC);
});

it('caches hasCourses boolean result', function (): void {
    $cache = app(TeacherCoursesCache::class);
    $calls = 0;

    $compute = function () use (&$calls): bool {
        $calls++;

        return true;
    };

    expect($cache->hasCourses(1, 2026, $compute))->toBeTrue()
        ->and($cache->hasCourses(1, 2026, $compute))->toBeTrue()
        ->and($calls)->toBe(1);
});

it('invalidates all variants by bumping the generation', function (): void {
    $cache = app(TeacherCoursesCache::class);
    $before = $cache->key(1, 2026);

    TeacherCoursesCache::invalidate(1, 2026);

    expect($cache->key(1, 2026))->not->toBe($before);
});

it('invalidates via ClassScheduleCacheObserver on save', function (): void {
    $schedule = ClassSchedule::factory()->create();
    $cache = app(TeacherCoursesCache::class);
    $before = $cache->key((int) $schedule->teacher_id, (int) $schedule->year_id);

    $schedule->update(['classroom' => 'A-101']);

    expect($cache->key((int) $schedule->teacher_id, (int) $schedule->year_id))->not->toBe($before);
});

it('only caches primitive arrays, never Eloquent models', function (): void {
    $cache = app(TeacherCoursesCache::class);

    $courses = $cache->courses(1, 2026, fn (): array => [
        ['subject_id' => 1, 'subject_name' => 'Ciencias'],
    ]);

    $stored = Cache::get($cache->key(1, 2026));

    expect($courses)->toBeArray()
        ->and($stored)->toBeArray()
        ->and($stored[0]['subject_name'])->toBe('Ciencias')
        ->and($stored[0])->toBeArray();
});
