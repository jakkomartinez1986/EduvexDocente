<?php

use App\Models\User;
use App\Services\Identity\SuperAdminService;
use App\Services\Identity\UserPasswordResetService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Super administrador unico y cierre del registro publico.
 *
 * La cedula del super administrador vive en el entorno (SUPER_ADMIN_DNI) y
 * nunca en el codigo. Mientras no exista esa cuenta el registro publico
 * sigue disponible; en cuanto existe, las rutas de registro responden 404 y
 * el enlace "Sign up" desaparece del formulario de acceso.
 */
beforeEach(function (): void {
    $this->skipUnlessFortifyHas(Features::registration());

    foreach (['ADMIN', 'SUPER-ADMIN'] as $name) {
        Role::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['description' => $name]
        );
    }
});

/**
 * Ejecuta el alta desde el formulario publico de registro.
 */
function registrarUsuario(TestCase $test, string $dni = '0102030400', string $email = 'test@example.com'): TestResponse
{
    return $test->post(route('register.store'), [
        'name' => 'John',
        'lastname' => 'Doe',
        'dni' => $dni,
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
}

/**
 * Crea la cuenta de super administrador directamente en la base.
 */
function superAdminUser(): User
{
    $user = User::factory()->create();

    app(SuperAdminService::class)->promote($user);

    return $user->fresh();
}

it('mantiene abierto el registro publico mientras no exista un super administrador', function (): void {
    $this->get(route('register'))->assertOk();

    registrarUsuario($this)->assertSessionHasNoErrors();

    expect(User::where('dni', '0102030400')->exists())->toBeTrue();
});

it('cierra el registro publico cuando ya existe un super administrador', function (): void {
    superAdminUser();

    $this->get(route('register'))->assertNotFound();

    registrarUsuario($this, '0203040500', 'otro@example.com')->assertNotFound();

    expect(User::where('dni', '0203040500')->exists())->toBeFalse();
});

it('oculta el enlace de registro del formulario de acceso cuando existe un super administrador', function (): void {
    $this->get(route('login'))->assertSee(route('register'));

    superAdminUser();

    $this->get(route('login'))->assertDontSee(route('register'));
});

it('otorga el rol SUPER-ADMIN a la cedula configurada en el entorno', function (): void {
    config(['eduvex.super_admin_dni' => '0102030400']);

    registrarUsuario($this, '0102030400')->assertSessionHasNoErrors();

    $user = User::where('dni', '0102030400')->firstOrFail();

    expect($user->hasRole(SuperAdminService::ROLE_NAME))->toBeTrue()
        ->and($user->status)->toBe(1)
        ->and($user->email_verified_at)->not->toBeNull();
});

it('no otorga el rol SUPER-ADMIN cuando el entorno no define la cedula', function (): void {
    config(['eduvex.super_admin_dni' => null]);

    registrarUsuario($this, '0102030400')->assertSessionHasNoErrors();

    expect(User::where('dni', '0102030400')->firstOrFail()->hasRole(SuperAdminService::ROLE_NAME))->toBeFalse();
});

it('no admite un segundo super administrador', function (): void {
    config(['eduvex.super_admin_dni' => '0102030400']);

    registrarUsuario($this, '0102030400')->assertSessionHasNoErrors();

    registrarUsuario($this, '0203040500', 'otro@example.com')->assertNotFound();

    $superAdmins = User::whereHas('roles', fn ($query) => $query->where('name', SuperAdminService::ROLE_NAME))->count();

    expect($superAdmins)->toBe(1);
});

it('no permite desactivar la cuenta del super administrador', function (): void {
    $superAdmin = superAdminUser();
    $admin = User::factory()->create();
    $admin->assignRole('ADMIN');

    Livewire::actingAs($admin)
        ->test('pages::system.identity.users.index')
        ->call('toggleStatus', $superAdmin);

    expect($superAdmin->fresh()->status)->toBe(1);
});

it('no permite quitar el rol SUPER-ADMIN al editar la cuenta', function (): void {
    $superAdmin = superAdminUser();

    Livewire::actingAs($superAdmin)
        ->test('pages::system.identity.users.edit', ['id' => $superAdmin->id])
        ->call('update');

    $superAdmin->refresh();

    expect($superAdmin->hasRole(SuperAdminService::ROLE_NAME))->toBeTrue()
        ->and($superAdmin->status)->toBe(1);
});

it('crea la cuenta de super administrador desde consola', function (): void {
    config(['eduvex.super_admin_dni' => '0102030400']);

    $this->artisan('make:super-admin')->assertSuccessful();

    $user = User::where('dni', '0102030400')->firstOrFail();

    expect($user->hasRole(SuperAdminService::ROLE_NAME))->toBeTrue()
        ->and($user->status)->toBe(1)
        ->and($user->must_change_password)->toBeTrue()
        ->and(Hash::check(UserPasswordResetService::defaultPasswordFor('0102030400'), $user->password))->toBeTrue();
});

it('promueve a super administrador la cuenta existente con la cedula configurada', function (): void {
    config(['eduvex.super_admin_dni' => '0102030400']);

    $user = User::factory()->create(['dni' => '0102030400']);

    $this->artisan('make:super-admin')->assertSuccessful();

    expect($user->fresh()->hasRole(SuperAdminService::ROLE_NAME))->toBeTrue();
});

it('aborta la creacion del super administrador si ya existe uno', function (): void {
    superAdminUser();

    config(['eduvex.super_admin_dni' => '0102030400']);

    $this->artisan('make:super-admin')->assertFailed();

    expect(User::where('dni', '0102030400')->exists())->toBeFalse();
});

it('no publica la cedula del super administrador en el codigo', function (): void {
    $dni = config('eduvex.super_admin_dni');

    if (! is_string($dni) || $dni === '') {
        $this->markTestSkipped('SUPER_ADMIN_DNI no esta definido en el entorno de pruebas.');
    }

    $files = array_merge(
        File::allFiles(base_path('app')),
        File::allFiles(base_path('config')),
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        expect(str_contains($file->getContents(), $dni))->toBeFalse();
    }
});
