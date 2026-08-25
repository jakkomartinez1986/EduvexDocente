<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function trimesterAdminUser(): User
{
    $role = Role::firstOrCreate(
        ['name' => 'SUPER-ADMIN', 'guard_name' => 'web'],
        ['description' => 'Super Administrador'],
    );

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('el trimestre en curso aparece primero en el selector de la administracion de notificaciones', function () {
    $now = now();

    $yearId = DB::table('scolar_years')->insertGetId([
        'year_name' => '2026-2027',
        'start_date' => $now->copy()->subDays(120)->toDateString(),
        'end_date' => $now->copy()->addDays(200)->toDateString(),
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    foreach ([
        ['Trimestre Pasado', $now->copy()->subDays(90)->toDateString(), $now->copy()->subDays(30)->toDateString()],
        ['Trimestre Actual', $now->copy()->subDays(10)->toDateString(), $now->copy()->addDays(20)->toDateString()],
        ['Trimestre Futuro', $now->copy()->addDays(40)->toDateString(), $now->copy()->addDays(110)->toDateString()],
    ] as [$name, $start, $end]) {
        DB::table('academic_periods')->insert([
            'year_id' => $yearId,
            'trimester_name' => $name,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $this->actingAs(trimesterAdminUser());

    $content = $this->get('/system/teacher/notifications')
        ->assertOk()
        ->getContent();

    $posActual = mb_strpos((string) $content, 'Trimestre Actual');
    $posPasado = mb_strpos((string) $content, 'Trimestre Pasado');
    $posFuturo = mb_strpos((string) $content, 'Trimestre Futuro');

    expect($posActual)->toBeInt()
        ->and($posPasado)->toBeInt()
        ->and($posFuturo)->toBeInt()
        ->and($posActual)->toBeLessThan($posPasado)
        ->and($posActual)->toBeLessThan($posFuturo);
});

test('sin trimestre en curso los periodos conservan su orden cronologico', function () {
    $now = now();

    $yearId = DB::table('scolar_years')->insertGetId([
        'year_name' => '2027-2028',
        'start_date' => $now->copy()->addDays(300)->toDateString(),
        'end_date' => $now->copy()->addDays(600)->toDateString(),
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    foreach ([
        ['Primer Periodo', $now->copy()->addDays(310)->toDateString(), $now->copy()->addDays(380)->toDateString()],
        ['Segundo Periodo', $now->copy()->addDays(400)->toDateString(), $now->copy()->addDays(470)->toDateString()],
    ] as [$name, $start, $end]) {
        DB::table('academic_periods')->insert([
            'year_id' => $yearId,
            'trimester_name' => $name,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $this->actingAs(trimesterAdminUser());

    $content = (string) $this->get('/system/teacher/notifications')
        ->assertOk()
        ->getContent();

    $posPrimero = mb_strpos($content, 'Primer Periodo');
    $posSegundo = mb_strpos($content, 'Segundo Periodo');

    expect($posPrimero)->toBeInt()
        ->and($posSegundo)->toBeInt()
        ->and($posPrimero)->toBeLessThan($posSegundo);
});
