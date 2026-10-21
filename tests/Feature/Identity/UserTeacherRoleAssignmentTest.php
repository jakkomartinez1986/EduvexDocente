<?php

use App\Models\Identity\Users\Teacher;
use App\Models\Security\Authorizations\Role;
use App\Models\User;
use Livewire\Livewire;

/**
 * Asignacion del rol DOCENTE desde la actualizacion de usuario.
 *
 * El rol DOCENTE necesita su perfil en `teachers` (1-a-1 con `users`): sin el
 * el docente no aparece en el modulo de Docentes ni tiene navegacion ni
 * horario docente.
 */
beforeEach(function (): void {
    foreach (['ADMIN', 'SUPER-ADMIN', 'DOCENTE', 'TUTOR', 'REPRESENTANTE', 'ESTUDIANTE'] as $name) {
        Role::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['description' => $name]
        );
    }
});

function adminForTeacherRole(): User
{
    $user = User::factory()->create();
    $user->assignRole('ADMIN');

    return $user;
}

function roleId(string $name): int
{
    return (int) Role::where('name', $name)->value('id');
}

it('ofrece el rol DOCENTE en la actualizacion de usuario', function (): void {
    $admin = adminForTeacherRole();
    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $user->id])
        ->assertSee('DOCENTE');
});

it('no ofrece los roles que dependen de otro modulo', function (): void {
    $admin = adminForTeacherRole();
    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $user->id])
        ->assertDontSee('TUTOR')
        ->assertDontSee('REPRESENTANTE')
        ->assertDontSee('ESTUDIANTE');
});

it('asigna el rol DOCENTE y crea el perfil de docente', function (): void {
    $admin = adminForTeacherRole();
    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $user->id])
        ->set('selectedRoles', [roleId('DOCENTE')])
        ->call('update');

    $user->refresh();

    expect($user->hasRole('DOCENTE'))->toBeTrue()
        ->and(Teacher::where('user_id', $user->id)->count())->toBe(1);

    $teacher = Teacher::where('user_id', $user->id)->first();

    expect($teacher->teacher_code)->toStartWith('DOC-')
        ->and($teacher->hire_date)->not->toBeNull();
});

it('no duplica el perfil de docente cuando la cuenta ya es docente', function (): void {
    $admin = adminForTeacherRole();
    $teacher = Teacher::factory()->create();
    $user = $teacher->user;

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $user->id])
        ->set('selectedRoles', [roleId('DOCENTE')])
        ->call('update');

    expect(Teacher::where('user_id', $user->id)->count())->toBe(1)
        ->and(Teacher::find($teacher->id)?->teacher_code)->toBe($teacher->teacher_code);
});

it('conserva el rol DOCENTE de un docente existente al actualizarlo', function (): void {
    $admin = adminForTeacherRole();
    $teacher = Teacher::factory()->create();
    $user = $teacher->user;
    $user->assignRole('DOCENTE');

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $user->id])
        ->call('update');

    expect($user->fresh()->hasRole('DOCENTE'))->toBeTrue();
});

it('no borra el perfil de docente al quitar el rol', function (): void {
    $admin = adminForTeacherRole();
    $teacher = Teacher::factory()->create();
    $user = $teacher->user;
    $user->assignRole('DOCENTE');

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $user->id])
        ->set('selectedRoles', [roleId('ADMIN')])
        ->call('update');

    expect($user->fresh()->hasRole('DOCENTE'))->toBeFalse()
        ->and(Teacher::where('user_id', $user->id)->count())->toBe(1);
});

it('genera codigos de docente distintos al asignar el rol a dos usuarios', function (): void {
    $admin = adminForTeacherRole();
    $first = User::factory()->create();
    $second = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $first->id])
        ->set('selectedRoles', [roleId('DOCENTE')])
        ->call('update');

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $second->id])
        ->set('selectedRoles', [roleId('DOCENTE')])
        ->call('update');

    $codes = Teacher::whereIn('user_id', [$first->id, $second->id])->pluck('teacher_code');

    expect($codes)->toHaveCount(2)
        ->and($codes->unique())->toHaveCount(2);
});
