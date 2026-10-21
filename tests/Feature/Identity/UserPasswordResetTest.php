<?php

use App\Models\Identity\Users\Teacher;
use App\Models\User;
use App\Services\Identity\UserPasswordResetService;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Reseteo administrativo de contraseñas (ADMIN / SUPER-ADMIN).
 *
 * El reseteo devuelve la credencial al valor inicial del sistema
 * (`{cedula}passsword`) y obliga al usuario a cambiarla al ingresar.
 */
beforeEach(function (): void {
    foreach (['ADMIN', 'SUPER-ADMIN', 'DOCENTE'] as $name) {
        Role::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['description' => $name]
        );
    }
});

function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('ADMIN');

    return $user;
}

it('restablece la contraseña del usuario al valor inicial derivado de su cédula', function (): void {
    $admin = adminUser();
    $target = User::factory()->create([
        'password' => Hash::make('clave-anterior'),
        'must_change_password' => false,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.index')
        ->call('executeResetPassword', ['id' => $target->id]);

    $target->refresh();

    expect(Hash::check($target->dni.UserPasswordResetService::DEFAULT_PASSWORD_SUFFIX, $target->password))->toBeTrue()
        ->and($target->must_change_password)->toBeTrue();
});

it('permite a un super-admin resetear la contraseña', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('SUPER-ADMIN');

    $target = User::factory()->create(['must_change_password' => false]);

    Livewire::actingAs($superAdmin)
        ->test('pages::system.identity.users.index')
        ->call('executeResetPassword', ['id' => $target->id]);

    expect($target->fresh()->must_change_password)->toBeTrue()
        ->and(Hash::check($target->dni.'passsword', $target->fresh()->password))->toBeTrue();
});

it('no permite el reseteo a usuarios sin rol administrativo', function (): void {
    $docente = User::factory()->create();
    $docente->assignRole('DOCENTE');

    $target = User::factory()->create(['must_change_password' => false]);
    $originalPassword = $target->password;

    Livewire::actingAs($docente)
        ->test('pages::system.identity.users.index')
        ->call('executeResetPassword', ['id' => $target->id]);

    expect($target->fresh()->password)->toBe($originalPassword)
        ->and($target->fresh()->must_change_password)->toBeFalse();
});

it('solicita confirmación antes de resetear la contraseña', function (): void {
    $admin = adminUser();
    $target = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.index')
        ->call('confirmResetPassword', $target->id)
        ->assertDispatched('showConfirm');
});

it('devuelve la contraseña temporal y limpia el remember token', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);

    $password = app(UserPasswordResetService::class)->resetToDefault($user);

    $user->refresh();

    expect($password)->toBe($user->dni.'passsword')
        ->and($user->remember_token)->toBeNull()
        ->and($user->must_change_password)->toBeTrue();
});

it('obliga al usuario reseteado a cambiar la contraseña al ingresar', function (): void {
    $admin = adminUser();
    $target = User::factory()->create(['must_change_password' => false]);

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.index')
        ->call('executeResetPassword', ['id' => $target->id]);

    $this->actingAs($target->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('password.change'));
});

it('protege las rutas de usuarios para roles no administrativos', function (): void {
    $docente = User::factory()->create();
    $docente->assignRole('DOCENTE');

    $this->actingAs($docente)
        ->get(route('system.identity.users.index'))
        ->assertForbidden();
});

it('permite el acceso a las rutas de usuarios a un administrador', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('system.identity.users.index'))
        ->assertOk()
        ->assertSee('Resetear contraseña');
});

it('resetea la contraseña desde el detalle del usuario', function (): void {
    $admin = adminUser();
    $target = User::factory()->create(['must_change_password' => false]);

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.show', ['id' => $target->id])
        ->assertSee('Resetear contraseña')
        ->call('executeResetPassword', ['id' => $target->id]);

    expect($target->fresh()->must_change_password)->toBeTrue()
        ->and(Hash::check($target->dni.'passsword', $target->fresh()->password))->toBeTrue();
});

it('resetea la contraseña de un docente desde la gestión de docentes', function (): void {
    $admin = adminUser();

    $teacher = Teacher::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::system.identity.teachers.index')
        ->call('executeResetPassword', ['id' => $teacher->id]);

    $user = $teacher->user->fresh();

    expect($user->must_change_password)->toBeTrue()
        ->and(Hash::check($user->dni.'passsword', $user->password))->toBeTrue();
});
