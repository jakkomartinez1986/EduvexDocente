<?php

namespace App\Services\Queue;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/**
 * Observabilidad del ecosistema de colas/workers (workers-monitor). Centraliza
 * la lectura de señales de liveness y backlog para la pantalla de super-admin:
 *   1) Heartbeat: `QueueHeartbeat` escribe `queue:worker:heartbeat` cada minuto;
 *      si la marca se queda antigua (> STALE_AFTER_SECONDS) el worker se reporta
 *      caído/estancado.
 *   2) Backlog: estados `pending/delayed/reserved` de cada cola vía el facade.
 *   3) Fallos: jobs en `failed_jobs` (reintento y limpieza desde la UI).
 *   4) Comandos de activación listos para copiar/pegar por entorno.
 *
 * Este servicio ES la única fuente de estas consultas: la SFC solo delega aquí.
 */
class WorkerMonitorService
{
    /**
     * Marca de caché del último heartbeat del worker.
     */
    public const string HEARTBEAT_KEY = 'queue:worker:heartbeat';

    /**
     * Segundos tras los que una marca antigua se considera worker caído.
     * El heartbeat corre cada minuto; 180 s tolera ~2 fallos de cadencia.
     */
    public const int STALE_AFTER_SECONDS = 180;

    /**
     * Segundos a partir de los cuales un job pendiente más antiguo se marca
     * "estancado" (backlog sin procesar aunque el worker esté vivo).
     */
    public const int STALE_JOB_SECONDS = 300;

    /**
     * @var array<int, string>
     */
    public const array QUEUES = ['default', 'notifications', 'reports', 'sync'];

    /**
     * Resultado agregado de liveness.
     *
     * @return array{worker_up: bool, last_heartbeat_at: ?int, seconds_since_heartbeat: ?int, health: string, queues: array<int, array>}
     */
    public function status(): array
    {
        $now = (int) now()->getTimestamp();
        $heartbeat = $this->lastHeartbeatAt();

        $secondsSince = $heartbeat === null ? null : $now - $heartbeat;

        $workerUp = $heartbeat !== null && $secondsSince <= self::STALE_AFTER_SECONDS;

        $queues = collect(self::QUEUES)
            ->map(fn (string $name): array => $this->queueSnapshot($name, $now))
            ->all();

        $hasStaleBacklog = (new Collection($queues))->contains(fn ($queue) => $queue['oldest_pending_age'] !== null && $queue['oldest_pending_age'] > self::STALE_JOB_SECONDS);

        $health = $workerUp
            ? ($hasStaleBacklog ? 'warning' : 'ok')
            : 'down';

        return [
            'worker_up' => $workerUp,
            'last_heartbeat_at' => $heartbeat,
            'seconds_since_heartbeat' => $secondsSince,
            'health' => $health,
            'queues' => $queues,
        ];
    }

    /**
     * Última marca de heartbeat (timestamp unix) o null si nunca hubo.
     */
    public function lastHeartbeatAt(): ?int
    {
        return Cache::get(self::HEARTBEAT_KEY);
    }

    /**
     * Foto de una cola.
     *
     * @return array{name: string, pending: int, delayed: int, reserved: int, oldest_pending_age: ?int, oldest_pending_stale: bool}
     */
    public function queueSnapshot(string $name, ?int $now = null): array
    {
        $oldest = Queue::creationTimeOfOldestPendingJob($name);
        $age = $oldest === null ? null : ($now ?? (int) now()->getTimestamp()) - $oldest;

        return [
            'name' => $name,
            'pending' => Queue::pendingSize($name),
            'delayed' => Queue::delayedSize($name),
            'reserved' => Queue::reservedSize($name),
            'oldest_pending_age' => $age,
            'oldest_pending_stale' => $age !== null && $age > self::STALE_JOB_SECONDS,
        ];
    }

    /**
     * Conteo de jobs fallidos.
     */
    public function failedCount(): int
    {
        return $this->failedJobs()->count();
    }

    /**
     * Jobs fallidos más recientes.
     */
    public function failedJobs(int $limit = 20): Collection
    {
        return (new Collection($this->failer()->all()))
            ->map(fn ($job): array => $this->asArray($job))
            ->sortByDesc(fn ($job) => $job['failed_at'] ?? 0)
            ->take($limit)
            ->map(fn (array $job): array => $this->normalizeFailedJob($job));
    }

    /**
     * Reintenta un job fallido: lo devuelve a su cola (mismo connection/queue)
     * y lo olvida del registro de fallos. Idempotente: si el id ya no existe, no-op.
     */
    public function retryFailed(mixed $id): void
    {
        $failer = $this->failer();

        $job = $failer->find($id);

        if (is_null($job) || is_null($job->connection) || is_null($job->queue)) {
            return;
        }

        Queue::connection($job->connection)->pushRaw($this->resetAttempts($job->payload), $job->queue, $this->queueableOptions($job));

        $failer->forget($id);
    }

    /**
     * Limpia todos los jobs fallidos (o los de las últimas N horas).
     */
    public function flushFailed(?int $hours = null): void
    {
        $this->failer()->flush($hours);
    }

    /**
     * Descarta un job fallido sin reintentarlo.
     */
    public function forgetFailed(mixed $id): void
    {
        $this->failer()->forget($id);
    }

    /**
     * Comandos de activación listos para copiar/pegar.
     *
     * @return array{env: string, queue_work: string, schedule_work: string, monitor: string, supervisor_note: string}
     */
    public function activation(): array
    {
        $queues = implode(',', self::QUEUES);

        $env = app()->environment();

        return [
            'env' => $env,
            'queue_work' => "php artisan queue:work --queue={$queues} --tries=3 --sleep=3",
            'schedule_work' => 'php artisan schedule:work',
            'monitor' => "php artisan queue:monitor {$queues} --max=100",
            'supervisor_note' => 'En producción, supervisor/el monitor de procesos debe reiniciar `queue:work` y `schedule:work` automáticamente (ver /docs).',
        ];
    }

    /**
     * @return FailedJobProviderInterface
     */
    protected function failer()
    {
        return app('queue.failer');
    }

    protected function resetAttempts(string $payload): string
    {
        $payload = json_decode($payload, true);

        if (isset($payload['attempts'])) {
            $payload['attempts'] = 0;
        }

        return json_encode($payload);
    }

    /**
     * @param  \stdClass  $job
     */
    protected function queueableOptions($job): array
    {
        $queue = Queue::connection($job->connection);

        if (! method_exists($queue, 'getQueueableOptions')) {
            return [];
        }

        $payload = json_decode($job->payload, true);

        if (! isset($payload['data']['command'])) {
            return [];
        }

        return $queue->getQueueableOptions($this->instanceFromPayload($payload), $job->queue, $job->payload);
    }

    /**
     * @return mixed
     */
    protected function instanceFromPayload(array $payload)
    {
        if (str_starts_with($payload['data']['command'], 'O:')) {
            return unserialize($payload['data']['command']);
        }

        return unserialize(app(Encrypter::class)->decrypt($payload['data']['command']));
    }

    /**
     * Convierte un registro del failer (array o stdClass) a array.
     *
     * @param  array<mixed>|object  $job
     * @return array<mixed>
     */
    protected function asArray($job): array
    {
        return is_array($job) ? $job : (array) $job;
    }

    /**
     * @return array{id: mixed, connection: ?string, queue: ?string, payload: string, failed_at: mixed, exception: ?string, class: ?string}
     */
    protected function normalizeFailedJob(array $job): array
    {
        return [
            'id' => $job['id'] ?? null,
            'connection' => $job['connection'] ?? null,
            'queue' => $job['queue'] ?? null,
            'payload' => $job['payload'] ?? null,
            'failed_at' => $job['failed_at'] ?? null,
            'exception' => $job['exception'] ?? null,
            'class' => $this->extractJobName($job['payload'] ?? ''),
        ];
    }

    protected function extractJobName(string $payload): ?string
    {
        $payload = json_decode($payload, true);

        if (! $payload) {
            return null;
        }

        if (! isset($payload['data']['command'])) {
            return $payload['job'] ?? null;
        }

        if (! empty($payload['displayName']) && is_string($payload['displayName'])) {
            return $payload['displayName'];
        }

        preg_match('/"([^"]+)"/', $payload['data']['command'], $matches);

        return $matches[1] ?? $payload['job'] ?? null;
    }
}
