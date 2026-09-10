<?php

use App\Models\Setting\EducationalSettings\Grade;
use App\Support\Database\DatabaseDialect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verifica que el ordenamiento NULLS LAST portable produce el mismo
 * resultado en todos los motores: NULL al final en ASC.
 */
beforeEach(function (): void {
    Schema::dropIfExists('compat_sort_items');
    Schema::create('compat_sort_items', function ($table): void {
        $table->id();
        $table->string('value')->nullable();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('compat_sort_items');
});

it('ordena valores nulos al final (equivalente a NULLS LAST)', function (): void {
    DB::table('compat_sort_items')->insert([
        ['value' => 'Banana'],
        ['value' => null],
        ['value' => 'Manzana'],
        ['value' => 'Aguacate'],
        ['value' => null],
    ]);

    $rows = DB::table('compat_sort_items')
        ->orderByRaw(DatabaseDialect::nullsLastRaw('value', 'asc'))
        ->pluck('value')
        ->all();

    expect($rows)->toBe([
        'Aguacate',
        'Banana',
        'Manzana',
        null,
        null,
    ]);
});

it('ordena valores nulos al final en dirección DESC', function (): void {
    DB::table('compat_sort_items')->insert([
        ['value' => 'Banana'],
        ['value' => null],
        ['value' => 'Manzana'],
        ['value' => 'Aguacate'],
        ['value' => null],
    ]);

    $rows = DB::table('compat_sort_items')
        ->orderByRaw(DatabaseDialect::nullsLastRaw('value', 'desc'))
        ->pluck('value')
        ->all();

    expect($rows)->toBe([
        'Manzana',
        'Banana',
        'Aguacate',
        null,
        null,
    ]);
});

it('sin valores nulos el ordenamiento es equivalente a ORDER BY normal', function (): void {
    $grades = Grade::factory()->count(5)->create();

    $portable = Grade::query()
        ->orderByRaw(DatabaseDialect::nullsLastRaw('grade_name', 'asc'))
        ->pluck('id')
        ->all();

    $standard = Grade::query()
        ->orderBy('grade_name', 'asc')
        ->pluck('id')
        ->all();

    expect($portable)->toBe($standard);
});
