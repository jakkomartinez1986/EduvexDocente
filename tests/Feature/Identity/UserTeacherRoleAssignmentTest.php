<?php

use App\Models\Identity\Users\Teacher;
use App\Models\Security\Authorizations\Role;
use App\Models\User;
use Livewire\Livewire;

/**
 * Asignacion del rol DOCENTE desde la actualizacion de usuario.
 *
 * El rol DOCENTE solo se ofrece y se guarda para cuentas que ya tienen
 * perfil de docente (`teachers`, 1-a-1 con `users`): las importadas o las
 * creadas en el modulo de Docentes. Una cuenta que se registro por si sola
 * no tiene perfil, por lo que no ve la opcion y no puede quedarse con el
 * rol a medio camino.
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

it('ofrece el rol DOCENTE a un usuario que ya tiene perfil de docente', function (): void {
    $admin = adminForTeacherRole();
    $teacher = Teacher::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $teacher->user_id]);

    expect($component->get('availableRoles')->pluck('name')->all())->toContain('DOCENTE');
});

it('no ofrece el rol DOCENTE a un usuario sin perfil de docente', function (): void {
    $admin = adminForTeacherRole();
    $user = User::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $user->id]);

    expect($component->get('availableRoles')->pluck('name')->all())->not->toContain('DOCENTE');
});

it('no ofrece los roles que dependen de otro modulo', function (): void {
    $admin = adminForTeacherRole();
    $teacher = Teacher::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $teacher->user_id]);

    $names = $component->get('availableRoles')->pluck('name')->all();

    expect($names)->not->toContain('TUTOR')
        ->and($names)->not->toContain('REPRESENTANTE')
        ->and($names)->not->toContain('ESTUDIANTE');
});

it('asigna el rol DOCENTE a un usuario con perfil de docente', function (): void {
    $admin = adminForTeacherRole();
    $teacher = Teacher::factory()->create();
    $user = $teacher->user;

    expect($user->hasRole('DOCENTE'))->toBeFalse();

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $user->id])
        ->set('selectedRoles', [roleId('DOCENTE')])
        ->call('update');

    expect($user->fresh()->hasRole('DOCENTE'))->toBeTrue()
        ->and(Teacher::where('user_id', $user->id)->count())->toBe(1);
});

it('no asigna el rol DOCENTE a un usuario sin perfil de docente', function (): void {
    $admin = adminForTeacherRole();
    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $user->id])
        ->set('selectedRoles', [roleId('DOCENTE')])
        ->call('update');

    expect($user->fresh()->hasRole('DOCENTE'))->toBeFalse()
        ->and(Teacher::where('user_id', $user->id)->count())->toBe(0);
});

it('no crea el perfil de docente al asignar el rol', function (): void {
    $admin = adminForTeacherRole();
    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $user->id])
        ->set('selectedRoles', [roleId('DOCENTE')])
        ->call('update');

    expect(Teacher::where('user_id', $user->id)->count())->toBe(0);
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

it('no ofrece el rol DOCENTE a un docente con perfil dado de baja', function (): void {
    $admin = adminForTeacherRole();
    $teacher = Teacher::factory()->create();
    $teacher->delete();

    $component = Livewire::actingAs($admin)
        ->test('pages::system.identity.users.edit', ['id' => $teacher->user_id]);

    expect($component->get('availableRoles')->pluck('name')->all())->not->toContain('DOCENTE');
});
