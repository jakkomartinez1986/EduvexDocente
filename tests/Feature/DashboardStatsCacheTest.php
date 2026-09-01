<?php

declare(strict_types=1);

use App\Services\TeacherManagement\DashboardStatsCache;
use Illuminate\Support\Facades\Cache;

it('caches dashboard stats and reuses them without recomputing', function (): void {
    $cache = app(DashboardStatsCache::class);
    Cache::flush();

    $calls = 0;
    $compute = function () use (&$calls): array {
        $calls++;

        return [1 => 3, 2 => 12, 3 => 5, 4 => 8];
    };

    $first = $cache->counts(10, 2026, $compute);
    $second = $cache->counts(10, 2026, $compute);

    expect($first)->toBe([1 => 3, 2 => 12, 3 => 5, 4 => 8])
        ->and($second)->toBe($first)
        ->and($calls)->toBe(1)
        ->and(Cache::has($cache->key(10, 2026)))->toBeTrue();
});

it('separates cache entries by teacher and year', function (): void {
    $cache = app(DashboardStatsCache::class);
    Cache::flush();

    $calls = 0;
    $cache->counts(10, 2026, function () use (&$calls): array {
        $calls++;

        return [1 => 1];
    });

    $cache->counts(11, 2026, function () use (&$calls): array {
        $calls++;

        return [1 => 2];
    });

    expect($calls)->toBe(2)
        ->and(Cache::has($cache->key(10, 2026)))->toBeTrue()
        ->and(Cache::has($cache->key(11, 2026)))->toBeTrue();
});

it('forgets the stats cache for a teacher/year', function (): void {
    $cache = app(DashboardStatsCache::class);
    Cache::flush();

    $cache->counts(10, 2026, fn (): array => [1 => 1]);
    expect(Cache::has($cache->key(10, 2026)))->toBeTrue();

    $cache->forget(10, 2026);
    expect(Cache::has($cache->key(10, 2026)))->toBeFalse();
});
