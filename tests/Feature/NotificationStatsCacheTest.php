<?php

declare(strict_types=1);

use App\Models\Identity\Users\Student;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Services\TeacherManagement\NotificationStatsCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(fn () => Cache::flush());

it('caches notification stats and serves the cached value', function (): void {
    $cache = app(NotificationStatsCache::class);
    $calls = 0;

    $first = $cache->stats(2026, 1, ['trimester_id' => null], function () use (&$calls): array {
        $calls++;

        return ['total' => 10, 'attended' => 4, 'not_attended' => 6, 'printed' => 2];
    });

    $second = $cache->stats(2026, 1, ['trimester_id' => null], function () use (&$calls): array {
        $calls++;

        return ['total' => 10, 'attended' => 4, 'not_attended' => 6, 'printed' => 2];
    });

    expect($first)->toBe(['total' => 10, 'attended' => 4, 'not_attended' => 6, 'printed' => 2])
        ->and($second)->toBe($first)
        ->and($calls)->toBe(1)
        ->and(Cache::has($cache->key(2026, 1, ['trimester_id' => null])))->toBeTrue();
});

it('uses a different cache slot per filter combination and teacher scope', function (): void {
    $cache = app(NotificationStatsCache::class);

    $cache->stats(2026, 1, ['trimester_id' => null], fn (): array => ['total' => 1]);
    $cache->stats(2026, 1, ['trimester_id' => 7], fn (): array => ['total' => 2]);
    $cache->stats(2026, null, ['trimester_id' => null], fn (): array => ['total' => 3]);

    expect($cache->key(2026, 1, ['trimester_id' => null]))->not->toBe($cache->key(2026, 1, ['trimester_id' => 7]))
        ->and($cache->key(2026, 1, ['trimester_id' => null]))->not->toBe($cache->key(2026, null, ['trimester_id' => null]));
});

it('invalidates every filter variant by bumping the generation', function (): void {
    $cache = app(NotificationStatsCache::class);
    $keyUnfiltered = $cache->key(2026, 1, ['trimester_id' => null, 'nivel_id' => null]);
    $keyFiltered = $cache->key(2026, 1, ['trimester_id' => 7]);

    NotificationStatsCache::invalidate(2026, 1);

    expect($cache->key(2026, 1, ['trimester_id' => null, 'nivel_id' => null]))->not->toBe($keyUnfiltered)
        ->and($cache->key(2026, 1, ['trimester_id' => 7]))->not->toBe($keyFiltered);
});

it('also invalidates the admin view when a specific teacher writes a notification', function (): void {
    $cache = app(NotificationStatsCache::class);
    $adminKey = $cache->key(2026, null, ['trimester_id' => null]);

    NotificationStatsCache::invalidate(2026, 7);

    expect($cache->key(2026, null, ['trimester_id' => null]))->not->toBe($adminKey);
});

it('invalidates the stats cache on write via NotificationCacheObserver', function (): void {
    $ctx = academicContext();
    $cache = app(NotificationStatsCache::class);
    $filters = ['trimester_id' => null];
    $yearId = (int) $ctx['year']->id;

    $compute = fn (): array => [
        'total' => AcademicNotification::query()->where('year_id', $yearId)->count(),
        'attended' => 0,
        'not_attended' => 0,
        'printed' => 0,
    ];

    $before = $cache->key($yearId, null, $filters);

    expect($cache->stats($yearId, null, $filters, $compute)['total'])->toBe(0);

    $student = Student::factory()->create();

    AcademicNotification::create([
        'code' => 'NOT-OBS-'.Str::random(6),
        'notification_number' => 1,
        'type' => 'academico',
        'channel' => 'sistema',
        'message' => 'Mensaje de cache',
        'student_id' => $student->id,
        'grade_id' => $ctx['grade']->id,
        'teacher_id' => $ctx['teacher']->id,
        'year_id' => $yearId,
        'trimester_id' => $ctx['trimester']->id,
        'generated_date' => now()->toDateString(),
    ]);

    $after = $cache->key($yearId, null, $filters);

    expect($after)->not->toBe($before)
        ->and($cache->stats($yearId, null, $filters, $compute)['total'])->toBe(1);
});

it('only caches plain integers, never Eloquent models', function (): void {
    $cache = app(NotificationStatsCache::class);

    $stats = $cache->stats(2026, 1, ['trimester_id' => null], fn (): array => ['total' => 3, 'attended' => 1, 'not_attended' => 2, 'printed' => 0]);

    $stored = Cache::get($cache->key(2026, 1, ['trimester_id' => null]));

    expect($stats)->toBeArray()
        ->and($stored)->toBeArray()
        ->and($stored['total'])->toBeInt();
});
