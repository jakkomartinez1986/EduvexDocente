<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Sync\SyncTombstone;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;

/**
 * Fase 8 - Motor de sincronización (synchronization.md §3-§7):
 * - POST /sync/push: outbox del cliente delegando en los servicios REST.
 * - GET /sync/pull: colecciones incrementales con cursor opaco + tombstones.
 * - Conflictos §6 vía base_updated_at; éxito parcial por diseño.
 *
 * Los helpers syncContext/syncGradebookContext/pushPayload/pullUrl viven en
 * tests/Pest.php para compartirse entre suites (p.ej. ThrottlingTest).
 */
it('aplica la snapshot de asistencia del outbox y devuelve echo con summary', function (): void {
    $context = syncContext();
    [$a, $b] = $context['students'];
    $headers = bearerTokenFor($context['teacher']->user);

    $response = $this->postJson('/api/v1/sync/push', [
        'device_id' => (string) Str::uuid(),
        'operations' => [[
            'operation_id' => (string) Str::uuid(),
            'entity' => 'attendance_day',
            'action' => 'replace_day',
            'client_updated_at' => now()->toISOString(),
            'payload' => [
                'schedule_id' => $context['schedule']->id,
                'date' => now()->toDateString(),
                'classtopic' => 'Ecuaciones cuadráticas',
                'statuses' => [
                    (string) $a->id => 'A',
                    (string) $b->id => 'I',
                    (string) $context['students'][2]->id => 'P',
                ],
            ],
        ]],
    ], $headers);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.schedule_id', $context['schedule']->id)
        ->assertJsonPath('data.results.0.echo.summary.absent', 1)
        ->assertJsonPath('data.next_steps.pull_recommended', true);

    expect(Attendance::query()->count())->toBe(2);
});

it('es idempotente ante el reenvio del mismo lote', function (): void {
    $context = syncContext();
    [$a] = $context['students'];
    $headers = bearerTokenFor($context['teacher']->user);

    $body = [
        'device_id' => (string) Str::uuid(),
        'operations' => [[
            'operation_id' => (string) Str::uuid(),
            'entity' => 'attendance_day',
            'action' => 'replace_day',
            'payload' => [
                'schedule_id' => $context['schedule']->id,
                'date' => now()->toDateString(),
                'classtopic' => 'Tema estable',
                'statuses' => [(string) $a->id => 'J'],
            ],
        ]],
    ];

    $this->postJson('/api/v1/sync/push', $body, $headers)->assertOk();
    $second = $this->postJson('/api/v1/sync/push', $body, $headers)->assertOk();

    expect($second->json('data.results.0.status'))->toBe('accepted');
    expect(Attendance::query()->count())->toBe(1);
});

it('entrega upserts de asistencia en el pull y avanza el cursor', function (): void {
    $context = syncContext();
    [$a, $b] = $context['students'];
    $headers = bearerTokenFor($context['teacher']->user);

    // Primer pull: sin cambios, emite cursor base.
    $first = $this->getJson(pullUrl('attendance'), $headers)
        ->assertOk()
        ->assertJsonCount(0, 'data.changes.attendance.upserts');

    $cursor = $first->json('data.cursor');

    $this->postJson('/api/v1/sync/push', [
        'device_id' => (string) Str::uuid(),
        'operations' => [[
            'operation_id' => (string) Str::uuid(),
            'entity' => 'attendance_day',
            'action' => 'replace_day',
            'payload' => [
                'schedule_id' => $context['schedule']->id,
                'date' => now()->toDateString(),
                'classtopic' => 'Tema',
                'statuses' => [(string) $a->id => 'A', (string) $b->id => 'AI'],
            ],
        ]],
    ], $headers)->assertOk();

    $second = $this->getJson(pullUrl('attendance', $cursor), $headers)
        ->assertOk()
        ->assertJsonCount(2, 'data.changes.attendance.upserts');

    expect($second->json('data.changes.attendance.tombstones'))->toBeEmpty();

    $upsert = collect($second->json('data.changes.attendance.upserts'))
        ->firstWhere('student_id', $a->id);

    expect($upsert['status'])->toBe('A');
    expect($upsert['updated_at'])->not->toBeNull();

    // Avanzar el cursor no entrega filas nuevas: la re-entrega dentro del
    // mismo segundo es aceptable (el merge local es idempotente).
    $seenIds = collect($second->json('data.changes.attendance.upserts'))->pluck('id');

    $third = collect($this->getJson(pullUrl('attendance', $second->json('data.cursor')), $headers)
        ->assertOk()
        ->json('data.changes.attendance.upserts'))->pluck('id');

    expect($third->diff($seenIds))->toBeEmpty();
});

it('publica tombstones cuando la correccion a P elimina filas activas', function (): void {
    $context = syncContext();
    [$a] = $context['students'];
    $headers = bearerTokenFor($context['teacher']->user);
    $push = function (array $statuses) use ($context, $headers): void {
        $this->postJson('/api/v1/sync/push', [
            'device_id' => (string) Str::uuid(),
            'operations' => [[
                'operation_id' => (string) Str::uuid(),
                'entity' => 'attendance_day',
                'action' => 'replace_day',
                'payload' => [
                    'schedule_id' => $context['schedule']->id,
                    'date' => now()->toDateString(),
                    'classtopic' => 'Tema',
                    'statuses' => $statuses,
                ],
            ]],
        ], $headers)->assertOk();
    };

    $push([(string) $a->id => 'I']);

    $pullBefore = $this->getJson(pullUrl('attendance'), $headers)->assertOk();
    $cursor = $pullBefore->json('data.cursor');

    $push([(string) $a->id => 'P']);

    expect(Attendance::query()->count())->toBe(0);
    expect(SyncTombstone::query()->where('entity', 'attendance')->count())->toBe(1);

    $pullAfter = $this->getJson(pullUrl('attendance', $cursor), $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.changes.attendance.tombstones');

    $tombstone = $pullAfter->json('data.changes.attendance.tombstones.0');
    expect($tombstone['entity'])->toBe('attendance');
    expect($tombstone['id'])->toBe(Attendance::withTrashed()->value('id'));
});

it('aplica notas de actividad desde el push y las entrega por pull de gradebook', function (): void {
    $context = syncGradebookContext();
    [$a, $b] = $context['students'];
    $headers = bearerTokenFor($context['teacher']->user);

    $this->postJson('/api/v1/sync/push', [
        'device_id' => (string) Str::uuid(),
        'operations' => [[
            'operation_id' => (string) Str::uuid(),
            'entity' => 'activity_grade',
            'action' => 'upsert_batch',
            'payload' => [
                'activity_id' => $context['activity']->id,
                'grades' => [['student_id' => $a->id, 'grade' => 8.5], ['student_id' => $b->id, 'grade' => null]],
            ],
        ]],
    ], $headers)->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.updated', 2);

    $pull = $this->getJson(pullUrl('gradebook'), $headers)
        ->assertOk()
        ->assertJsonCount(2, 'data.changes.gradebook.upserts');

    $row = collect($pull->json('data.changes.gradebook.upserts'))->firstWhere('student_id', $a->id);
    expect($row['activity_id'])->toBe($context['activity']->id);
    expect($row['grade'])->toEqual(8.5);
});

it('rechaza con conflicto si otro autor escribio una nota mas nueva', function (): void {
    $context = syncGradebookContext();
    [$a] = $context['students'];
    $otherTeacher = User::factory()->create();

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'grade' => 9.0,
        'recorded_by' => $otherTeacher->id,
        'updated_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/sync/push', pushPayload(
        'activity_grade',
        'upsert_batch',
        [
            'base_updated_at' => now()->subHour()->toISOString(),
            'activity_id' => $context['activity']->id,
            'grades' => [['student_id' => $a->id, 'grade' => 7.5]],
        ],
    ), bearerTokenFor($context['teacher']->user));

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'conflict')
        ->assertJsonPath('data.results.0.server_record.student_id', $a->id)
        ->assertJsonPath('data.results.0.resolution_hint', 'server_newer');

    expect($response->json('data.results.0.server_record.grade'))->toEqual(9.0);
    expect(ActivityGrade::query()->where('student_id', $a->id)->value('grade'))->toEqual(9.0);
});

it('avisa overwritten_by_newer_same_author cuando el propio docente sobrescribe', function (): void {
    $context = syncGradebookContext();
    [$a] = $context['students'];
    $teacherUserId = $context['teacher']->user_id;

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'grade' => 6.0,
        'recorded_by' => $teacherUserId,
        'updated_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/sync/push', pushPayload(
        'activity_grade',
        'upsert_batch',
        [
            'base_updated_at' => now()->subHour()->toISOString(),
            'activity_id' => $context['activity']->id,
            'grades' => [['student_id' => $a->id, 'grade' => 8.0]],
        ],
    ), bearerTokenFor($context['teacher']->user));

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.notice', 'overwritten_by_newer_same_author');

    expect(ActivityGrade::query()->where('student_id', $a->id)->value('grade'))->toEqual(8.0);
});

it('resume los conflictos del lote en data.conflicts', function (): void {
    $context = syncGradebookContext();
    [$a] = $context['students'];
    $otherTeacher = User::factory()->create();

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'grade' => 9.0,
        'recorded_by' => $otherTeacher->id,
        'updated_at' => now(),
    ]);

    $payload = pushPayload('activity_grade', 'upsert_batch', [
        'base_updated_at' => now()->subHour()->toISOString(),
        'activity_id' => $context['activity']->id,
        'grades' => [['student_id' => $a->id, 'grade' => 7.5]],
    ]);

    $response = $this->postJson('/api/v1/sync/push', $payload, bearerTokenFor($context['teacher']->user));

    $response->assertOk()
        ->assertJsonCount(1, 'data.conflicts')
        ->assertJsonPath('data.conflicts.0.operation_id', $response->json('data.results.0.operation_id'))
        ->assertJsonPath('data.conflicts.0.entity', 'activity_grade');
});

it('aplica la snapshot con force true y audita la decision explicita', function (): void {
    // Spy del canal `sync`: syncLog() declara `: Logger`, así que el stub
    // devuelve un spy de Illuminate\Log\Logger en lugar del propio LogManager.
    $syncChannel = Mockery::spy(Logger::class);
    Log::spy();
    Log::shouldReceive('channel')->with('sync')->andReturn($syncChannel);

    $context = syncGradebookContext();
    [$a] = $context['students'];
    $otherTeacher = User::factory()->create();
    $deviceId = (string) Str::uuid();

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'grade' => 9.0,
        'recorded_by' => $otherTeacher->id,
        'updated_at' => now(),
    ]);

    $payload = pushPayload('activity_grade', 'upsert_batch', [
        'base_updated_at' => now()->subHour()->toISOString(),
        'activity_id' => $context['activity']->id,
        'grades' => [['student_id' => $a->id, 'grade' => 7.5]],
    ]);
    $payload['operations'][0]['force'] = true;
    $payload['device_id'] = $deviceId;

    $response = $this->postJson('/api/v1/sync/push', $payload, bearerTokenFor($context['teacher']->user));

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.forced', true)
        ->assertJsonCount(0, 'data.conflicts');

    expect(ActivityGrade::query()->where('student_id', $a->id)->value('grade'))->toEqual(7.5);

    Log::shouldHaveReceived('channel')->with('sync')->atLeast()->once();

    $syncChannel->shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $logContext): bool => $message === 'sync.push.forced_override'
            && $logContext['device_id'] === $deviceId
            && $logContext['server_record']['student_id'] === $a->id);
});

it('no permite force sobre reglas duras: ventana de calificacion cerrada', function (): void {
    $context = syncGradebookContext();
    [$a] = $context['students'];
    $otherTeacher = User::factory()->create();

    $context['trimester']->update([
        'grading_open_date' => now()->subDays(90)->toDateString(),
        'grading_close_date' => now()->subDays(5)->toDateString(),
    ]);

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'grade' => 9.0,
        'recorded_by' => $otherTeacher->id,
        'updated_at' => now(),
    ]);

    $payload = pushPayload('activity_grade', 'upsert_batch', [
        'base_updated_at' => now()->subHour()->toISOString(),
        'activity_id' => $context['activity']->id,
        'grades' => [['student_id' => $a->id, 'grade' => 7.5]],
    ]);
    $payload['operations'][0]['force'] = true;

    $response = $this->postJson('/api/v1/sync/push', $payload, bearerTokenFor($context['teacher']->user));

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'rejected')
        ->assertJsonPath('data.results.0.errors.period.0', 'El período de calificación está cerrado.');

    expect(ActivityGrade::query()->where('student_id', $a->id)->value('grade'))->toEqual(9.0);
});

it('bloquea escrituras duplicadas de nota a nivel de BD', function (): void {
    $context = syncGradebookContext();
    [$a] = $context['students'];

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'grade' => 8.0,
    ]);

    expect(fn (): object => ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'grade' => 5.0,
    ]))->toThrow(UniqueConstraintViolationException::class)
        ->and(ActivityGrade::query()->where('activity_id', $context['activity']->id)->count())->toEqual(1);
});

it('permite el exito parcial dentro del mismo lote', function (): void {
    $context = syncGradebookContext();
    [$a] = $context['students'];

    $response = $this->postJson('/api/v1/sync/push', [
        'device_id' => (string) Str::uuid(),
        'operations' => [
            [
                'operation_id' => (string) Str::uuid(),
                'entity' => 'activity_grade',
                'action' => 'upsert_batch',
                'payload' => [
                    'activity_id' => $context['activity']->id,
                    'grades' => [['student_id' => $a->id, 'grade' => 9.5]],
                ],
            ],
            [
                'operation_id' => (string) Str::uuid(),
                'entity' => 'activity_grade',
                'action' => 'upsert_batch',
                'payload' => [
                    'activity_id' => $context['activity']->id,
                    'grades' => [['student_id' => 999999, 'grade' => 5.0]],
                ],
            ],
        ],
    ], bearerTokenFor($context['teacher']->user))->assertOk();

    $results = collect($response->json('data.results'));

    expect($results->pluck('status')->all())->toBe(['accepted', 'rejected']);
    expect($results[0]['echo']['updated'])->toBe(1);
    expect($results[1]['errors'])->toHaveKey('grades');
});

it('rechaza entidades prohibidas para sync MVP (D-03)', function (): void {
    $context = syncGradebookContext();

    $this->postJson('/api/v1/sync/push', pushPayload('activity', 'create', [
        'name' => 'Nueva actividad offline',
    ]), bearerTokenFor($context['teacher']->user))
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'rejected');
});

it('valida el lote: maximo 200 operaciones', function (): void {
    $context = syncContext();

    $operations = [];
    foreach (range(1, 201) as $i) {
        $operations[] = [
            'operation_id' => (string) Str::uuid(),
            'entity' => 'attendance_day',
            'action' => 'replace_day',
            'payload' => ['x' => $i],
        ];
    }

    $this->postJson('/api/v1/sync/push', [
        'device_id' => (string) Str::uuid(),
        'operations' => $operations,
    ], bearerTokenFor($context['teacher']->user))->assertStatus(422)
        ->assertJsonValidationErrors(['operations']);
});

it('exige las abilities cross-module de sync', function (): void {
    $context = syncContext();
    $headers = bearerTokenWithAbilities($context['teacher']->user, ['auth.me']);

    $this->get(pullUrl(), $headers)
        ->assertForbidden()
        ->assertJsonPath('meta.code', 'insufficient_abilities');

    $this->postJson('/api/v1/sync/push', [
        'device_id' => (string) Str::uuid(),
        'operations' => [],
    ], $headers)->assertForbidden();
});

it('rechaza un cursor invalido con 422', function (): void {
    $context = syncContext();

    $this->getJson(pullUrl('attendance', 'no-es-un-cursor'), bearerTokenFor($context['teacher']->user))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cursor']);
});

it('responde 403 para usuarios sin perfil docente', function (): void {
    $context = syncContext();
    $plainUser = User::factory()->create();
    attachApiModulePermissions($plainUser);

    // Payload válido: la validación pasa y el 403 viene del perfil faltante.
    $this->postJson('/api/v1/sync/push', [
        'device_id' => (string) Str::uuid(),
        'operations' => [[
            'operation_id' => (string) Str::uuid(),
            'entity' => 'attendance_day',
            'action' => 'replace_day',
            'payload' => [
                'schedule_id' => $context['schedule']->id,
                'date' => now()->toDateString(),
                'classtopic' => 'Tema',
                'statuses' => [(string) $context['students'][0]->id => 'A'],
            ],
        ]],
    ], bearerTokenFor($plainUser))->assertForbidden();
});
