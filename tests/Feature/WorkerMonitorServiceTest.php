<?php

declare(strict_types=1);

use App\Jobs\QueueHeartbeat;
use App\Services\Queue\WorkerMonitorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Cache::flush();
    config(['queue.default' => 'database']);
    config(['queue.connections.database.table' => 'jobs']);
});

it('reporta worker caído sin heartbeat', function (): void {
    $status = app(WorkerMonitorService::class)->status();

    expect($status['worker_up'])->toBeFalse()
        ->and($status['health'])->toBe('down')
        ->and($status['last_heartbeat_at'])->toBeNull()
        ->and(count($status['queues']))->toBe(4);
});

it('reporta worker activo con heartbeat reciente', function (): void {
    Cache::put(WorkerMonitorService::HEARTBEAT_KEY, now()->getTimestamp());

    $status = app(WorkerMonitorService::class)->status();

    expect($status['worker_up'])->toBeTrue()
        ->and($status['health'])->toBe('ok');
});

it('reporta worker caído con heartbeat antiguo', function (): void {
    Cache::put(WorkerMonitorService::HEARTBEAT_KEY, now()->subSeconds(400)->getTimestamp());

    $status = app(WorkerMonitorService::class)->status();

    expect($status['worker_up'])->toBeFalse()
        ->and($status['health'])->toBe('down')
        ->and($status['seconds_since_heartbeat'])->toBeGreaterThan(180);
});

it('mide el backlog pendiente de una cola', function (): void {
    Queue::connection('database')->push((object) null, [], 'notifications');

    $snapshot = app(WorkerMonitorService::class)->queueSnapshot('notifications');

    expect($snapshot['pending'])->toBe(1)
        ->and($snapshot['oldest_pending_stale'])->toBeFalse();
});

it('marca un job pendiente antiguo como estancado', function (): void {
    $queue = Queue::connection('database');
    $queue->push((object) null, [], 'notifications');

    DB::table('jobs')->update(['available_at' => now()->subMinutes(10)->getTimestamp()]);

    $snapshot = app(WorkerMonitorService::class)->queueSnapshot('notifications');

    expect($snapshot['pending'])->toBe(1)
        ->and($snapshot['oldest_pending_age'])->toBeGreaterThan(300)
        ->and($snapshot['oldest_pending_stale'])->toBeTrue();
});

it('lista jobs fallidos y permite reenviarlos', function (): void {
    $queue = Queue::connection('database');
    $queue->push(new QueueHeartbeat, [], 'default');

    $row = DB::table('jobs')->first();
    $failer = app('queue.failer');

    $failer->log('database', $row->queue, $row->payload, 'TestException: boom');

    DB::table('jobs')->where('id', $row->id)->delete();

    $service = app(WorkerMonitorService::class);
    $failed = $service->failedJobs();

    expect($service->failedCount())->toBe(1)
        ->and($failed->first()['exception'])->toContain('boom')
        ->and($failed->first()['class'])->not->toBeNull();

    $service->retryFailed($failed->first()['id']);

    expect($service->failedCount())->toBe(0)
        ->and(DB::table('jobs')->where('queue', 'default')->count())->toBe(1);
});

it('no expone el payload ni la excepción completa en el listado de fallidos', function (): void {
    $queue = Queue::connection('database');
    $queue->push(new QueueHeartbeat, [], 'default');

    $row = DB::table('jobs')->first();
    $failer = app('queue.failer');

    $longException = 'TestException: '.str_repeat('x', 5000);
    $failer->log('database', $row->queue, $row->payload, $longException);

    DB::table('jobs')->where('id', $row->id)->delete();

    $failed = app(WorkerMonitorService::class)->failedJobs()->first();

    expect($failed)->not->toHaveKeys(['payload', 'connection'])
        ->and(strlen($failed['exception']))->toBeLessThan(200);
});

it('permite olvidar un job fallido sin reenviarlo', function (): void {
    $failer = app('queue.failer');
    $failer->log('database', 'default', json_encode([
        'uuid' => (string) Str::uuid(),
        'displayName' => 'App\Jobs\QueueHeartbeat',
        'job' => 'App\Jobs\QueueHeartbeat',
    ]), 'TestException: discard');

    $service = app(WorkerMonitorService::class);
    $failed = $service->failedJobs();

    $service->forgetFailed($failed->first()['id']);

    expect($service->failedCount())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('genera comandos de activación por entorno', function (): void {
    $activation = app(WorkerMonitorService::class)->activation();

    expect($activation['queue_work'])->toContain('--queue=default,notifications,reports,sync')
        ->and($activation['schedule_work'])->toBe('php artisan schedule:work');
});
