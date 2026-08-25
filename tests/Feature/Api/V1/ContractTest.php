<?php

use App\Models\User;

/**
 * Limpieza de contrato (API_ROADMAP §11 Fase 1):
 * - H-15: la vista del libro vive SOLO en GET /academic/gradebook;
 *   GET /grades quedó eliminada.
 * - H-14: los 403 se emiten siempre vía excepción renderizada al envelope.
 * - H-05/H-12: configuración y variables de entorno documentadas.
 */
it('expone la descarga offline en /academic/gradebook/download', function (): void {
    $context = academicContext();

    $response = $this->get('/api/v1/academic/gradebook/download', bearerTokenFor($context['teacher']->user));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.year_id', $context['year']->id);

    expect($response->json('data'))
        ->toHaveKeys(['year_id', 'generated_at', 'blocks', 'exams', 'projects', 'supplementary_exams'])
        ->and($response->json('data.blocks'))->toBe([]);
});

it('elimina el alias duplicado GET /grades (H-15)', function (): void {
    $teacher = academicContext()['teacher'];

    $response = $this->get('/api/v1/grades?subject_id=1&grade_id=1&trimester_id=1', bearerTokenFor($teacher->user));

    $response->assertNotFound()
        ->assertJsonPath('success', false);
});

it('elimina el alias duplicado GET /settings (H-02)', function (): void {
    $teacher = academicContext()['teacher'];

    $this->get('/api/v1/settings', bearerTokenFor($teacher->user))
        ->assertNotFound()
        ->assertJsonPath('success', false);

    $this->get('/api/v1/configuration', bearerTokenFor($teacher->user))
        ->assertOk();
});

it('responde 403 con envelope cuando el usuario no es docente (H-14)', function (): void {
    $context = academicContext();
    $headers = bearerTokenFor(User::factory()->create());

    $viewUrl = '/api/v1/academic/gradebook?'.http_build_query([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
    ]);

    foreach ([
        $viewUrl,
        '/api/v1/academic/gradebook/download',
        '/api/v1/teachermanagement/attendances',
    ] as $endpoint) {
        $this->get($endpoint, $headers)
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'El usuario autenticado no tiene un perfil de docente.')
            ->assertJsonPath('meta.api_version', 'v1');
    }
});

it('mantiene el codigo password_change_required tras unificar los 403 con excepciones', function (): void {
    $user = User::factory()->create([
        'password' => 'Password123!',
        'must_change_password' => true,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'login' => $user->email,
        'password' => 'Password123!',
    ])
        ->assertForbidden()
        ->assertJsonPath('meta.code', 'password_change_required')
        ->assertJsonMissing(['access_token']);
});
