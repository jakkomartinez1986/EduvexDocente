<?php

declare(strict_types=1);

use App\Jobs\SendChannelMessageJob;
use App\Models\Identity\Users\Student;
use App\Models\Incidents\NotificationChannel;
use App\Services\Messaging\MessagingManager;
use App\Services\Messaging\SendResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

function hardeningSeedNotification(): array
{
    $context = academicContext();

    $student = Student::factory()->create();

    $now = now();

    $notificationId = DB::table('academic_notifications')->insertGetId([
        'code' => 'NOT-HARD-1',
        'notification_number' => 1,
        'type' => 'academico',
        'channel' => 'sistema',
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'teacher_id' => $context['teacher']->id,
        'year_id' => $context['year']->id,
        'trimester_id' => $context['trimester']->id,
        'message' => 'Prueba de hardening',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [...$context, 'notificationId' => $notificationId];
}

it('configura reintentos, backoff, timeout y cola de notificaciones', function (): void {
    Queue::fake();

    $job = new SendChannelMessageJob('whatsapp', '593999999999', 'Hola');

    expect($job->tries)->toBe(5);
    expect($job->backoff)->toBe([30, 60, 120, 240]);
    expect($job->timeout)->toBe(180);
});

it('enruta el job a la cola notifications', function (): void {
    Queue::fake();

    SendChannelMessageJob::dispatch('whatsapp', '593999999999', 'Hola');

    Queue::assertPushedOn('notifications', SendChannelMessageJob::class);
});

it('único por canal y destinatario y mensaje', function (): void {
    Cache::flush();
    Queue::fake();

    SendChannelMessageJob::dispatch('telegram', 'CHATID', 'Mensaje único');
    SendChannelMessageJob::dispatch('telegram', 'CHATID', 'Mensaje único');

    Queue::assertPushedTimes(SendChannelMessageJob::class, 1);
});

it('no deduplica envíos distintos hacia el mismo destinatario', function (): void {
    Cache::flush();
    Queue::fake();

    SendChannelMessageJob::dispatch('telegram', 'CHATID', 'Mensaje A');
    SendChannelMessageJob::dispatch('telegram', 'CHATID', 'Mensaje B');

    Queue::assertPushedTimes(SendChannelMessageJob::class, 2);
});

it('failed marca el canal de notificación pendiente como fallido', function (): void {
    $context = hardeningSeedNotification();

    $row = NotificationChannel::query()->create([
        'notification_id' => $context['notificationId'],
        'channel' => 'whatsapp',
        'status' => 'pending',
    ]);

    $job = new SendChannelMessageJob('whatsapp', '593999999999', 'texto', null, null, $row->id);
    $job->failed(null);

    expect($row->refresh()->status)->toBe('failed');
    expect($row->sent_at)->not->toBeNull();
});

it('handle marca el canal como enviado y no toca filas que no estén pendientes', function (): void {
    $context = hardeningSeedNotification();
    $messaging = Mockery::mock(MessagingManager::class);

    $messaging->shouldReceive('send')
        ->once()
        ->with('telegram', 'CHATID', 'texto', null, null)
        ->andReturn(SendResult::ok('42'));

    $row = NotificationChannel::query()->create([
        'notification_id' => $context['notificationId'],
        'channel' => 'telegram',
        'status' => 'pending',
    ]);

    (new SendChannelMessageJob('telegram', 'CHATID', 'texto', null, null, $row->id))->handle($messaging);

    expect($row->refresh()->status)->toBe('sent');
    expect($row->sent_at)->not->toBeNull();
});
