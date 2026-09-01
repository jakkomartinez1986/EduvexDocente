<?php

declare(strict_types=1);

use App\Models\Security\Authorizations\Permission as AppPermission;
use App\Models\Security\Authorizations\Role as AppRole;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Services\AcademicYearService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

function cacheKey(string $key): string
{
    return 'eduvex:'.app()->environment().':'.$key;
}

it('caches and nulls the active academic year', function (): void {
    Cache::flush();

    ScolarYear::factory()->create(['year_name' => '2025']);
    $active = ScolarYear::factory()->active()->create(['year_name' => '2026']);

    $service = app(AcademicYearService::class);

    expect($service->getActiveYear()->id)->toBe($active->id)
        ->and($service->getActiveYearId())->toBe($active->id)
        ->and(Cache::has(cacheKey('academic:active-year')))->toBeTrue();
});

it('invalidates the active academic year cache on save and delete', function (): void {
    Cache::flush();

    $active = ScolarYear::factory()->active()->create(['year_name' => '2026']);
    $service = app(AcademicYearService::class);

    expect($service->getActiveYear()->id)->toBe($active->id)
        ->and(Cache::has(cacheKey('academic:active-year')))->toBeTrue();

    $newYear = ScolarYear::factory()->active()->create(['year_name' => '2027']);
    expect($service->getActiveYear()->id)->toBe($newYear->id)
        ->and(Cache::has(cacheKey('academic:active-year')))->toBeTrue();

    $newYear->delete();
    expect($service->getActiveYear()->id)->toBe($active->id)
        ->and(Cache::has(cacheKey('academic:active-year')))->toBeTrue();
});

it('invalidates the active academic year cache when a period is saved', function (): void {
    Cache::flush();

    $year = ScolarYear::factory()->active()->create(['year_name' => '2026']);
    $service = app(AcademicYearService::class);

    expect($service->getActiveYearId())->toBe($year->id)
        ->and(Cache::has(cacheKey('academic:active-year')))->toBeTrue();

    AcademicPeriod::factory()->create(['year_id' => $year->id]);

    expect(Cache::has(cacheKey('academic:active-year')))->toBeFalse();
});

it('flushes the Spatie permission cache when a role or permission changes', function (): void {
    Cache::flush();

    $registrar = app(PermissionRegistrar::class);
    $registrar->clearPermissionsCollection();
    $name = 'CACHE-PROBE-'.Str::uuid();
    $role = AppRole::create(['name' => $name, 'description' => 'Probe']);
    $permission = AppPermission::create([
        'name' => 'ver-probe',
        'label' => 'Ver Probe',
        'module' => 'probe',
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo($permission);

    expect($registrar->getPermissions()->count())->toBe(1);
    expect(Cache::has($registrar->cacheKey))->toBeTrue();

    $role->update(['name' => $name.'-2']);
    expect(Cache::has($registrar->cacheKey))->toBeFalse();

    Cache::flush();
    $registrar->clearPermissionsCollection();
    expect($registrar->getPermissions()->count())->toBe(1);
    expect(Cache::has($registrar->cacheKey))->toBeTrue();

    $permission->delete();
    expect(Cache::has($registrar->cacheKey))->toBeFalse();
});

it('lets the base Spatie models also flush the permission cache', function (): void {
    Cache::flush();

    $registrar = app(PermissionRegistrar::class);
    $registrar->clearPermissionsCollection();
    SpatiePermission::create([
        'name' => 'ver-base-probe',
        'label' => 'Ver Base Probe',
        'module' => 'probe',
        'guard_name' => 'web',
    ]);

    expect(Cache::has($registrar->cacheKey))->toBeFalse();
});
