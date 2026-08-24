<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fase 11 - Producción: observabilidad del sync (API_ROADMAP §11).
 * - Canal dedicado `sync` (config/logging.php) con resumen estructurado
 *   de lote (sync.push.batch) y trazabilidad de pull (sync.pull).
 * - 413 con envelope estándar cuando el cuerpo excede post_max_size.
 */
it('resume cada lote del push en sync.push.batch', function (): void {
    Log::spy();
    Log::shouldReceive('channel')->with('sync')->andReturnSelf();

    $context = syncContext();
    [$a, $b] = $context['students'];

    // Lote mixto: 1 accepted + 1 rejected (entidad prohibida D-03).
    $this->postJson('/api/v1/sync/push', [
        'device_id' => ($deviceId = (string) Str::uuid()),
        'operations' => [
            [
                'operation_id' => (string) Str::uuid(),
                'entity' => 'attendance_day',
                'action' => 'replace_day',
                'client_updated_at' => now()->toISOString(),
                'payload' => [
                    'schedule_id' => $context['schedule']->id,
                    'date' => now()->toDateString(),
                    'classtopic' => 'Tema',
                    'statuses' => [(string) $a->id => 'P'],
                ],
            ],
            [
                'operation_id' => (string) Str::uuid(),
                'entity' => 'activity',
                'action' => 'upsert_batch',
                'client_updated_at' => now()->toISOString(),
                'payload' => ['grades' => [['student_id' => $b->id, 'grade' => 5]]],
            ],
        ],
    ], bearerTokenFor($context['teacher']->user))->assertOk();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $event, array $logContext): bool {
            if ($event !== 'sync.push.batch') {
                return false;
            }

            return $logContext['total'] === 2
                && $logContext['accepted'] === 1
                && $logContext['rejected'] === 1
                && $logContext['conflict'] === 0
                && $logContext['forced'] === 0
                && array_key_exists('device_id', $logContext)
                && array_key_exists('duration_ms', $logContext);
        });
});

it('registra volumen y duracion del pull en sync.pull', function (): void {
    Log::spy();
    Log::shouldReceive('channel')->with('sync')->andReturnSelf();

    $context = syncContext();
    [$a] = $context['students'];

    $this->postJson('/api/v1/sync/push', pushPayload('attendance_day', 'replace_day', [
        'schedule_id' => $context['schedule']->id,
        'date' => now()->toDateString(),
        'classtopic' => 'Tema',
        'statuses' => [(string) $a->id => 'A'],
    ]), bearerTokenFor($context['teacher']->user))->assertOk();

    app('auth')->forgetGuards();

    $this->getJson(pullUrl(), bearerTokenFor($context['teacher']->user))
        ->assertOk()
        ->assertJsonCount(1, 'data.changes.attendance.upserts');

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $event, array $logContext): bool => $event === 'sync.pull'
            && $logContext['collections'] === ['attendance', 'gradebook']
            && $logContext['upserts'] === 1
            && $logContext['tombstones'] === 0
            && array_key_exists('duration_ms', $logContext));
});

it('responde 413 con envelope estandar si el cuerpo excede post_max_size', function (): void {
    $this->withServerVariables(['CONTENT_LENGTH' => (string) (6 * 1024 * 1024)]);

    $response = $this->postJson('/api/v1/sync/push', ['device_id' => (string) Str::uuid()]);

    $response->assertStatus(413)
        ->assertJsonPath('success', false)
        ->assertJsonPath('meta.code', 'payload_too_large');
});
