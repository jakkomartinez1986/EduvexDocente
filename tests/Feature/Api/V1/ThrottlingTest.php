<?php

use Illuminate\Support\Str;

/**
 * Fase 10 - Throttling y resiliencia (API_ROADMAP §8.4, synchronization.md §7.7):
 * - Throttle global de la API v1 por token (120/min configurable).
 * - Throttle específico de POST /sync/push (60/min configurable).
 * - El 429 conserva Retry-After para el backoff del cliente.
 * - Los contadores son independientes por docente.
 *
 * phpunit.xml eleva los límites base para no contaminar otras suites;
 * aquí se ajustan con config() porque los closures se evalúan por request.
 */
function throttlingAttendancePayload(array $context): array
{
    [$a, $b] = $context['students'];

    return [
        'device_id' => (string) Str::uuid(),
        'operations' => [[
            'operation_id' => (string) Str::uuid(),
            'entity' => 'attendance_day',
            'action' => 'replace_day',
            'client_updated_at' => now()->toISOString(),
            'payload' => [
                'schedule_id' => $context['schedule']->id,
                'date' => now()->toDateString(),
                'classtopic' => 'Tema de prueba',
                'statuses' => [
                    (string) $a->id => 'A',
                    (string) $b->id => 'P',
                ],
            ],
        ]],
    ];
}

it('aplica el throttle especifico a /sync/push y devuelve 429 con Retry-After', function (): void {
    config(['api.rate_limit.sync_push_per_minute' => 2]);

    $context = syncContext();
    $headers = bearerTokenFor($context['teacher']->user);

    $this->postJson('/api/v1/sync/push', throttlingAttendancePayload($context), $headers)
        ->assertOk();
    $this->postJson('/api/v1/sync/push', throttlingAttendancePayload($context), $headers)
        ->assertOk();

    $blocked = $this->postJson('/api/v1/sync/push', throttlingAttendancePayload($context), $headers);

    $blocked->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('success', false)
        ->assertJsonPath('meta.api_version', 'v1');
});

it('aplica el throttle global de la API v1 por token', function (): void {
    config(['api.rate_limit.v1_per_minute' => 1]);

    $context = syncContext();
    $headers = bearerTokenFor($context['teacher']->user);

    $this->getJson(pullUrl(), $headers)->assertOk();

    $blocked = $this->getJson(pullUrl(), $headers);

    $blocked->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('message', 'Demasiadas solicitudes. Inténtelo nuevamente más tarde.');
});

it('mantiene contadores independientes por docente en sync push', function (): void {
    config(['api.rate_limit.sync_push_per_minute' => 1]);

    $agotado = syncContext();
    $otro = syncContext();

    // Un token por docente: cada llamada a bearerTokenFor emitiría un token
    // nuevo y el contador clavearía distinto.
    $headersAgotado = bearerTokenFor($agotado['teacher']->user);
    $headersOtro = bearerTokenFor($otro['teacher']->user);

    $this->postJson('/api/v1/sync/push', throttlingAttendancePayload($agotado), $headersAgotado)
        ->assertOk();

    // Artefacto del entorno de pruebas: el guard sanctum persiste entre
    // requests del mismo proceso y devolvería al docente anterior.
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/sync/push', throttlingAttendancePayload($agotado), $headersAgotado)
        ->assertStatus(429);

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/sync/push', throttlingAttendancePayload($otro), $headersOtro)
        ->assertOk();
});
