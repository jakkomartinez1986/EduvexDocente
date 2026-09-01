<?php

declare(strict_types=1);

use App\Models\Security\Authorizations\Permission as AppPermission;
use App\Models\Security\Authorizations\Role as AppRole;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Services\AcademicYearService;
use App\Services\SchoolConfigService;
use App\Services\StaticCatalogService;
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
        ->and(Cache::get(cacheKey('academic:active-year')))->toBe($active->id);
});

it('does not cache Eloquent models (avoids __PHP_Incomplete_Class on file cache)', function (): void {
    Cache::flush();

    $active = ScolarYear::factory()->active()->create(['year_name' => '2026']);
    $service = app(AcademicYearService::class);

    $service->getActiveYearId();
    $cached = Cache::get(cacheKey('academic:active-year'));

    expect($cached)->toBeInt()
        ->and($cached)->toBe($active->id)
        ->and($service->getActiveYear())->toBeInstanceOf(ScolarYear::class);
});

it('invalidates the active academic year cache on save and delete', function (): void {
    Cache::flush();

    $active = ScolarYear::factory()->active()->create(['year_name' => '2026']);
    $service = app(AcademicYearService::class);

    expect($service->getActiveYear()->id)->toBe($active->id)
        ->and(Cache::get(cacheKey('academic:active-year')))->toBe($active->id);

    $newYear = ScolarYear::factory()->active()->create(['year_name' => '2027']);
    expect($service->getActiveYear()->id)->toBe($newYear->id)
        ->and(Cache::get(cacheKey('academic:active-year')))->toBe($newYear->id);

    $newYear->delete();
    expect($service->getActiveYear()->id)->toBe($active->id)
        ->and(Cache::get(cacheKey('academic:active-year')))->toBe($active->id);
});

it('invalidates the active academic year cache when a period is saved', function (): void {
    Cache::flush();

    $year = ScolarYear::factory()->active()->create(['year_name' => '2026']);
    $service = app(AcademicYearService::class);

    expect($service->getActiveYearId())->toBe($year->id)
        ->and(Cache::get(cacheKey('academic:active-year')))->toBe($year->id);

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

it('caches the active school id as a primitive', function (): void {
    Cache::flush();

    School::factory()->inactive()->create(['name_school' => 'Escuela Inactiva B']);
    $school = School::factory()->create(['name_school' => 'Escuela Activa']);

    $service = app(SchoolConfigService::class);

    expect($service->getActiveSchool()?->id)->toBe($school->id)
        ->and(Cache::get(cacheKey('school:active-id')))->toBe($school->id);
});

it('does not cache the School Eloquent model (avoids __PHP_Incomplete_Class)', function (): void {
    Cache::flush();

    $school = School::factory()->create(['name_school' => 'Escuela Activa']);
    $service = app(SchoolConfigService::class);

    $service->getActiveSchoolId();

    expect(Cache::get(cacheKey('school:active-id')))->toBeInt()
        ->and($service->getActiveSchool())->toBeInstanceOf(School::class);
});

it('invalidates the active school cache on save and delete', function (): void {
    Cache::flush();

    $school = School::factory()->create(['name_school' => 'Escuela Activa']);
    $service = app(SchoolConfigService::class);

    expect($service->getActiveSchool()?->id)->toBe($school->id)
        ->and(Cache::has(cacheKey('school:active-id')))->toBeTrue();

    $newSchool = School::factory()->create(['name_school' => 'Nueva Escuela']);
    expect($service->getActiveSchool()?->id)->toBe($newSchool->id);

    $newSchool->delete();
    expect($service->getActiveSchool()?->id)->toBe($school->id)
        ->and(Cache::get(cacheKey('school:active-id')))->toBe($school->id);
});

it('caches static catalogs as serializable arrays (not Eloquent models)', function (): void {
    Cache::flush();

    $grade = Grade::factory()->create(['grade_name' => 'Octavo']);
    $subject = Subject::factory()->create(['subject_name' => 'Matemática']);

    $service = app(StaticCatalogService::class);
    $catalogs = $service->catalogs();

    expect($catalogs['grades'])->toBeArray()
        ->and($catalogs['subjects'])->toBeArray()
        ->and(Cache::has(cacheKey('catalog:static')))->toBeTrue();

    $cached = Cache::get(cacheKey('catalog:static'));
    expect($cached['grades'])->toBeArray()
        ->and($cached['subjects'])->toBeArray();
});

it('invalidates the static catalog cache when a model changes', function (): void {
    Cache::flush();

    $grade = Grade::factory()->create(['grade_name' => 'Octavo']);
    $service = app(StaticCatalogService::class);

    expect($service->catalogs()['grades'])->toBeArray()
        ->and(Cache::has(cacheKey('catalog:static')))->toBeTrue();

    $newGrade = Grade::factory()->create(['grade_name' => 'Primero']);
    expect(Cache::has(cacheKey('catalog:static')))->toBeFalse()
        ->and($service->catalogs()['grades'])->toBeArray()
        ->and(Cache::has(cacheKey('catalog:static')))->toBeTrue();

    $newGrade->delete();
    expect(Cache::has(cacheKey('catalog:static')))->toBeFalse();
});
