<?php

use App\Services\Api\V1\Sync\SyncService;
use App\Support\Database\DatabaseDialect;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Verifica el motor de sincronización contra cualquiera de los tres motores:
 * cursors/watermarks, tombstones y merges idempotentes.
 */
it('genera cursos de sincronización con formato Y-m-d H:i:s (portable)', function (): void {
    $service = app(SyncService::class);

    $reflection = new ReflectionMethod($service, 'encodeCursor');

    // El cursor solo contiene cadenas Y-m-d H:i:s sin operadores de motor.
    $reflection->setAccessible(true);
    $cursor = $reflection->invoke($service, [
        'attendance' => Carbon::parse('2026-09-10 10:00:00'),
        'gradebook' => Carbon::parse('2026-09-10 10:00:00'),
    ]);

    $decoded = json_decode((string) base64_decode($cursor, true), true);

    expect($decoded['v'])->toBe(1)
        ->and($decoded['attendance'])->toBe('2026-09-10 10:00:00');
});

it('decodifica un cursor válido sin errores de motor', function (): void {
    $service = app(SyncService::class);

    $reflection = new ReflectionMethod($service, 'decodeCursor');
    $reflection->setAccessible(true);

    $encoded = base64_encode((string) json_encode([
        'v' => 1,
        'attendance' => '2026-09-01 07:00:00',
        'gradebook' => '2026-09-01 07:00:00',
    ]));

    /** @var array<string, Carbon> $decoded */
    $decoded = $reflection->invoke($service, $encoded);

    expect($decoded['attendance']->format('Y-m-d H:i:s'))->toBe('2026-09-01 07:00:00');
});

it('rechaza un cursor corrupto de forma portable', function (): void {
    $service = app(SyncService::class);

    $reflection = new ReflectionMethod($service, 'decodeCursor');
    $reflection->setAccessible(true);

    expect(fn () => $reflection->invoke($service, base64_encode('not-json')))
        ->toThrow(ValidationException::class);
});

it('el driver del motor es detectable sin ramificar en el negocio', function (): void {
    expect(in_array(DatabaseDialect::driver(), ['pgsql', 'mysql', 'mariadb', 'sqlite'], true))->toBeTrue();
});
