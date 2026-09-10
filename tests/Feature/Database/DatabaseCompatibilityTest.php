<?php

use App\Support\Database\DatabaseDialect;
use Illuminate\Support\Facades\DB;

it('detecta el nombre del driver activo', function (): void {
    $driverName = DB::connection()->getDriverName();

    $expected = $driverName;
    if ($driverName === 'mysql' && str_contains(strtolower((string) DB::connection()->getServerVersion()), 'mariadb')) {
        $expected = DatabaseDialect::DRIVER_MARIADB;
    }

    expect(DatabaseDialect::driver())->toBe($expected);
});

it('clasifica correctamente las familias de motores', function (): void {
    $dummy = DatabaseDialect::driver();

    expect(in_array($dummy, [
        DatabaseDialect::DRIVER_PGSQL,
        DatabaseDialect::DRIVER_MYSQL,
        DatabaseDialect::DRIVER_MARIADB,
        'sqlite',
    ], true))->toBeTrue();
});

it('genera una expresión NULLS LAST portable', function (): void {
    $expr = DatabaseDialect::nullsLastRaw('grade_name', 'asc');

    expect($expr)->toBe('(grade_name IS NULL) ASC, grade_name ASC');
});

it('genera una expresión NULLS LAST portable en dirección DESC', function (): void {
    $expr = DatabaseDialect::nullsLastRaw('grade_name', 'desc');

    expect($expr)->toBe('(grade_name IS NULL) ASC, grade_name DESC');
});

it('construye una búsqueda case-insensitive portable con LOWER()', function (): void {
    $sql = DatabaseDialect::ilike(
        DB::table('users'),
        'name',
        '%josé%',
    )->toSql();

    expect($sql)->toContain('LOWER')
        ->and($sql)->toContain('LIKE');
});
