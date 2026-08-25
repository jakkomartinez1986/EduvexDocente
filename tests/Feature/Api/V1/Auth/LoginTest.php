<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('autentica por correo electrónico y emite token Bearer', function (): void {
    $user = User::factory()->create([
        'email' => 'docente@eduvex.edu.ec',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'docente@eduvex.edu.ec',
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'docente@eduvex.edu.ec')
        ->assertJsonPath('meta.api_version', 'v1');

    expect($response->json('data.access_token'))->not->toBeEmpty();
    expect($response->json('data.expires_at'))->not->toBeEmpty();
    expect($user->fresh()->tokens()->count())->toBe(1);
});

it('autentica por número de DNI sin importar mayúsculas o espacios', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', [
        'login' => ' '.$user->dni.' ',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('data.user.dni', $user->dni);
});

it('normaliza el correo electrónico a minúsculas al autenticar', function (): void {
    $user = User::factory()->create(['email' => 'Docente@Eduvex.edu.ec']);

    $this->postJson('/api/v1/auth/login', [
        'login' => 'DOCENTE@eduvex.edu.ec',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('data.user.id', $user->id);
});

it('rechaza credenciales inválidas con 401 y mensaje genérico', function (): void {
    User::factory()->create(['password' => Hash::make('secret123')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'otro@eduvex.edu.ec',
        'password' => 'incorrecta',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Las credenciales proporcionadas no son válidas.');

    expect($response->json('errors'))->toBeNull();
});

it('rechaza usuarios desactivados como credenciales inválidas', function (): void {
    $user = User::factory()->create(['status' => 0]);

    $this->postJson('/api/v1/auth/login', [
        'login' => $user->dni,
        'password' => 'password',
    ])->assertStatus(401)
        ->assertJsonPath('message', 'Las credenciales proporcionadas no son válidas.');
});

it('bloquea usuarios con must_change_password con 403 y no emite token', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('success', false)
        ->assertJsonPath('meta.code', 'password_change_required');

    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('aplica rate limiting de 5 intentos por minuto al login', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', [
            'login' => $user->dni,
            'password' => 'incorrecta-'.$attempt,
        ])->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/login', [
        'login' => $user->dni,
        'password' => 'incorrecta-6',
    ])->assertStatus(429)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Demasiadas solicitudes. Inténtelo nuevamente más tarde.');
});

it('valida que login y password sean obligatorios', function (): void {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Los datos proporcionados no son válidos.')
        ->assertJsonValidationErrors(['login', 'password']);
});
