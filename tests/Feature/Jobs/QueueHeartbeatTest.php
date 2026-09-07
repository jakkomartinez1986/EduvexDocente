<?php

use App\Jobs\QueueHeartbeat;
use App\Services\Queue\WorkerMonitorService;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('escribe la marca de heartbeat al ejecutarse', function (): void {
    app(QueueHeartbeat::class)->handle();

    $heartbeat = Cache::get(WorkerMonitorService::HEARTBEAT_KEY);

    expect($heartbeat)->toBeInt()
        ->and($heartbeat)->toBeGreaterThanOrEqual(now()->subMinutes(1)->getTimestamp());
});

it('se enruta a la cola default', function (): void {
    $job = new QueueHeartbeat;

    expect($job->queue ?? 'default')->toBe('default');
});
