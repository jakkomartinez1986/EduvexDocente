<?php

use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Security\Authorizations\Permission;
use App\Models\Security\Authorizations\Role;
use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\User;
use App\Support\Database\DatabaseDialect;
use Illuminate\Database\Eloquent\Builder;

/**
 * Verifica que las búsquedas case-insensitive (antes ILIKE) sean
 * equivalentes y portables. En este entorno los tests corren contra
 * SQLite; los mismos query builders generan LOWER(...) LIKE LOWER(...)
 * que funciona en PostgreSQL, MySQL y MariaDB.
 */
it('busca roles insensibles a mayúsculas/minúsculas', function (): void {
    $role = Role::create(['name' => 'COORDINADOR', 'description' => 'Rol coordinador', 'guard_name' => 'web']);

    foreach (['coordinador', 'COORDINADOR', 'Coordinador'] as $query) {
        expect(Role::search($query)->pluck('id'))->toContain($role->id);
    }

    expect(Role::search('') instanceof Builder)->toBeTrue();
});

it('busca permisos insensibles a mayúsculas/minúsculas', function (): void {
    $permission = Permission::create([
        'name' => 'ver-reportes',
        'label' => 'Ver reportes',
        'module' => 'reports',
        'guard_name' => 'web',
    ]);

    foreach (['ver-reportes', 'VER-REPORTES', 'Ver-Reportes'] as $query) {
        expect(Permission::search($query)->pluck('id'))->toContain($permission->id);
    }
});

it('busca nombres con acentos y caracteres Unicode (ñ)', function (): void {
    $user = User::factory()->create([
        'name' => 'José',
        'lastname' => 'Muñoz',
    ]);

    $found = User::query()
        ->where(fn ($q) => DatabaseDialect::ilike($q, 'name', '%josé%'))
        ->pluck('id');

    expect($found)->toContain($user->id);
});

it('busca insensible a mayúsculas pero exigiendo el acento exacto', function (): void {
    $jose = User::factory()->create(['name' => 'José', 'lastname' => 'Peña']);
    $jose2 = User::factory()->create(['name' => 'Jose', 'lastname' => 'Peña']);

    $query = User::query()
        ->where(fn ($q) => DatabaseDialect::ilike($q, 'name', '%josé%'))
        ->pluck('name');

    // Comportamiento documentado: case-insensitive, accent-sensitive.
    // 'José' siempre coincide. 'Jose' solo coincide en MySQL/MariaDB cuya
    // collation utf8mb4_unicode_ci es accent-insensitive (en PostgreSQL y
    // SQLite los bytes difieren y NO coincide).
    //
    // User::setNameAttribute() normaliza a mayúsculas, por lo que el match
    // se compara en minúsculas para que la aserción no dependa del casing.
    $foundLower = collect($query)->map(fn (string $name): string => mb_strtolower($name));

    expect($foundLower->contains('josé'))->toBeTrue();

    $accentInsensitive = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    expect($foundLower->contains('jose'))->toBe($accentInsensitive);
});

it('genera SQL portable en los modelos de catálogo', function (): void {
    $items = collect();

    $items->push(['model' => School::class, 'column' => 'name_school']);
    $items->push(['model' => Shift::class, 'column' => 'shift_name']);
    $items->push(['model' => Nivel::class, 'column' => 'nivel_name']);
    $items->push(['model' => Grade::class, 'column' => 'grade_name']);
    $items->push(['model' => Area::class, 'column' => 'area_name']);
    $items->push(['model' => Subject::class, 'column' => 'subject_name']);
    $items->push(['model' => ScolarYear::class, 'column' => 'year_name']);
    $items->push(['model' => AcademicPeriod::class, 'column' => 'trimester_name']);
    $items->push(['model' => CalendarDay::class, 'column' => 'day_name']);

    foreach ($items as $item) {
        $sql = forward_static_call([$item['model'], 'query'])
            ->when(true, fn ($q) => DatabaseDialect::ilike($q, $item['column'], '%x%'))
            ->toSql();

        expect($sql)->toContain('LOWER')
            ->and($sql)->toContain('LIKE')
            ->and($sql)->not->toContain('ILIKE');
    }
});

it('construye búsquedas sin el operador ILIKE en ningún lugar', function (): void {
    $builder = Teacher::query()
        ->whereHas('user', fn ($q) => DatabaseDialect::ilike($q, 'users.name', '%peña%'));

    $sql = $builder->toSql();

    expect($sql)->not->toContain('ILIKE');
});

it('prueba búsqueda de estudiantes por código con case-insensitive', function (): void {
    $student = Student::factory()->create(['student_code' => 'ABC123']);

    $found = Student::query()
        ->when(true, fn ($q) => DatabaseDialect::ilike($q, 'student_code', '%abc123%'))
        ->pluck('id');

    expect($found)->toContain($student->id);
});
