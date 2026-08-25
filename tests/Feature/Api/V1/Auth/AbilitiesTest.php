<?php

use App\Models\Identity\Users\Teacher;
use Illuminate\Auth\AuthManager;

/**
 * Fase 2 - Auth endurecida:
 * - H-11: tokens con abilities por módulo (nada de ['*']).
 * - Gate token.ability en rutas de datos (403 insufficient_abilities).
 * - D2: must_change_password bloquea datos por API pero permite me/logout;
 *   tras rotar la contraseña el mismo token vuelve a operar.
 */
it('emite tokens con abilities por modulo segun los permisos del usuario', function (): void {
    $teacher = Teacher::factory()->create();
    $teacher->user->update(['password' => 'Password123!']);
    attachApiModulePermissions($teacher->user, ['grades']);

    $this->postJson('/api/v1/auth/login', [
        'login' => $teacher->user->email,
        'password' => 'Password123!',
    ])->assertOk();

    $token = $teacher->user->tokens()->latest('id')->first();

    expect($token->abilities)
        ->toContain('auth.me', 'auth.logout', 'configuration.read')
        ->toContain('grades.read', 'grades.write')
        ->toContain('students.read', 'sync.pull', 'sync.push')
        ->not->toContain('attendance.read', 'attendance.write', 'schedule.read');
});

it('rechaza con envelope cuando el token no tiene la ability requerida', function (): void {
    $context = academicContext();
    $headers = bearerTokenWithAbilities($context['teacher']->user, ['auth.me']);

    $this->get('/api/v1/teachermanagement/schedules', $headers)
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('meta.code', 'insufficient_abilities')
        ->assertJsonPath('meta.required_abilities', ['schedule.read']);
});

it('permite acceder cuando el token tiene la ability del modulo', function (): void {
    $context = academicContext();
    $headers = bearerTokenWithAbilities($context['teacher']->user, ['schedule.read']);

    $this->get('/api/v1/teachermanagement/schedules', $headers)->assertOk();
});

it('bloquea los datos por API con must_change_password pero permite me y logout', function (): void {
    $context = academicContext();
    $context['teacher']->user->update(['must_change_password' => true]);
    $headers = bearerTokenFor($context['teacher']->user);

    $this->get('/api/v1/configuration', $headers)
        ->assertForbidden()
        ->assertJsonPath('meta.code', 'password_change_required');

    $this->get('/api/v1/auth/me', $headers)->assertOk();

    $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();
});

it('restaura el acceso con el mismo token tras rotar la contrasena en la web', function (): void {
    $context = academicContext();
    $context['teacher']->user->update(['must_change_password' => true]);
    $headers = bearerTokenFor($context['teacher']->user);

    $this->get('/api/v1/configuration', $headers)->assertForbidden();

    // forceFill: la columna no está en $fillable del modelo.
    $context['teacher']->user->forceFill(['must_change_password' => false])->save();

    // El guard de Sanctum cachea al usuario entre requests DENTRO del mismo
    // test (en producción cada request es un ciclo nuevo). Se reinician los
    // guards para simular el siguiente request real con el MISMO token.
    $this->app->make(AuthManager::class)->forgetGuards();

    $this->get('/api/v1/configuration', $headers)->assertOk();
});
