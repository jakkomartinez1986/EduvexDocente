<?php

use App\Logging\RedactSensitiveProcessor;
use Monolog\Level;
use Monolog\LogRecord;

function redactedRecord(array $context, array $sensitiveKeys = []): LogRecord
{
    config(['logging.redaction' => $sensitiveKeys]);

    return (new RedactSensitiveProcessor)(
        new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'testing',
            level: Level::Error,
            message: 'test',
            context: $context,
        ),
    );
}

it('redacta valores de claves sensibles por defecto', function () {
    $record = redactedRecord([
        'password' => 'top-secret',
        'channel_token' => 'abc123',
    ]);

    expect($record->context['password'])->toBe('[REDACTED]')
        ->and($record->context['channel_token'])->toBe('[REDACTED]');
});

it('redacta de forma recursiva en contextos anidados', function () {
    $record = redactedRecord([
        'user' => [
            'name' => 'Juan',
            'password' => 'clave',
            'api_key' => 'k-123',
        ],
    ]);

    expect($record->context['user']['password'])->toBe('[REDACTED]')
        ->and($record->context['user']['api_key'])->toBe('[REDACTED]')
        ->and($record->context['user']['name'])->toBe('Juan');
});

it('respeta claves no sensibles', function () {
    $record = redactedRecord(['student_id' => 42, 'subject_name' => 'Matemática']);

    expect($record->context['student_id'])->toBe(42)
        ->and($record->context['subject_name'])->toBe('Matemática');
});

it('aplica claves adicionales de configuración logging.redaction', function () {
    $record = redactedRecord(
        ['boarding_pass' => 'BP-0001', 'password' => 'x'],
        ['boarding_pass'],
    );

    expect($record->context['boarding_pass'])->toBe('[REDACTED]')
        ->and($record->context['password'])->toBe('[REDACTED]');
});

it('redacta extra además de context', function () {
    $record = (new RedactSensitiveProcessor)(
        new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'testing',
            level: Level::Error,
            message: 'test',
            context: ['password' => 'a'],
            extra: ['api_token' => 'b'],
        ),
    );

    expect($record->context['password'])->toBe('[REDACTED]')
        ->and($record->extra['api_token'])->toBe('[REDACTED]');
});
