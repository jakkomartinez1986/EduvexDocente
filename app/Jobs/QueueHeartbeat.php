<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Heartbeat de liveness del worker (workers-monitor): se agenda cada minuto y
 * solo puede ejecutarse si un worker de cola está activo, por lo que su última
 * escritura (cache `queue:worker:heartbeat`) es la señal de "worker vivo".
 * Si el worker cae (o el scheduler deja de encolar), el timestamp se queda
 * antiguo y la página de monitoreo lo reporta como caído/estancado.
 */
class QueueHeartbeat implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 60;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'worker-heartbeat';
    }

    public function handle(): void
    {
        Cache::put('queue:worker:heartbeat', (int) now()->getTimestamp(), 600);
    }
}
