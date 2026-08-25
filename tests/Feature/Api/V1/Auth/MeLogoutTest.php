<?php

use App\Models\User;

it('devuelve el perfil del usuario autenticado en /auth/me', function (): void {
    $user = User::factory()->create(['name' => 'María', 'lastname' => 'Pérez']);

    $response = $this->getJson('/api/v1/auth/me', bearerTokenFor($user));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.full_name', $user->fresh()->full_name)
        ->assertJsonStructure(['data' => ['roles', 'permissions']]);
});

it('rechaza /auth/me sin token con envelope 401', function (): void {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'No autenticado. Token inválido o ausente.');
});

it('rechaza /auth/me con token inválido', function (): void {
    $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer token-inexistente'])
        ->assertStatus(401)
        ->assertJsonPath('success', false);
});

it('revoca el token actual al cerrar sesión', function (): void {
    $user = User::factory()->create();
    $headers = bearerTokenFor($user);

    $this->postJson('/api/v1/auth/logout', [], $headers)
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->fresh()->tokens()->count())->toBe(0);

    // El contenedor cachea el usuario autenticado en el guard entre
    // peticiones del mismo test; se limpia para validar el token real.
    $this->app->make('auth')->forgetGuards();

    $this->getJson('/api/v1/auth/me', $headers)
        ->assertStatus(401);
});

it('rechaza logout sin autenticación', function (): void {
    $this->postJson('/api/v1/auth/logout')
        ->assertStatus(401)
        ->assertJsonPath('success', false);
});
