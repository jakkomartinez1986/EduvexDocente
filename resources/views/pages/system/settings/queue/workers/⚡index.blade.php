<?php

declare(strict_types=1);

use App\Services\Queue\WorkerMonitorService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Monitoreo de Trabajadores')] class extends Component
{
    public array $status = [];

    public array $failed = [];

    public array $activation = [];

    public int $failedTotal = 0;

    public bool $hasHeartbeat = false;

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $service = app(WorkerMonitorService::class);

        $this->status = $service->status();
        $this->activation = $service->activation();
        $this->failed = $service->failedJobs(50)->all();
        $this->failedTotal = $service->failedCount();
        $this->hasHeartbeat = $service->lastHeartbeatAt() !== null;
    }

    public function retry(mixed $id): void
    {
        if (! $this->isAdmin()) {
            abort(403);
        }

        app(WorkerMonitorService::class)->retryFailed($id);

        $this->refresh();

        Flux::toast(variant: 'success', text: __('Trabajo reintentado.'));
    }

    public function forget(mixed $id): void
    {
        if (! $this->isAdmin()) {
            abort(403);
        }

        app(WorkerMonitorService::class)->forgetFailed($id);

        $this->refresh();

        Flux::toast(variant: 'success', text: __('Trabajo descartado.'));
    }

    public function flushFailed(): void
    {
        if (! $this->isAdmin()) {
            abort(403);
        }

        app(WorkerMonitorService::class)->flushFailed();

        $this->refresh();

        Flux::toast(variant: 'success', text: __('Fallos limpiados.'));
    }

    private function isAdmin(): bool
    {
        return auth()->user()?->hasRole(['SUPER-ADMIN', 'ADMIN']) ?? false;
    }

    public function queueBadgeColor(string $queue): string
    {
        return match ($queue) {
            'default' => 'zinc',
            'notifications' => 'sky',
            'reports' => 'violet',
            default => 'amber',
        };
    }
}; ?>
<div wire:poll.keep-alive="20000ms">
    <flux:heading size="text-xl" level="1">{{ __('Monitoreo de Trabajadores') }}</flux:heading>
    <flux:subheading>{{ __('Estado de los workers de cola, backlog y trabajos fallidos.') }}</flux:subheading>
    <div class="flex items-center gap-3 mt-2">
        <flux:button variant="outline" icon="arrow-path" wire:click="refresh">Actualizar</flux:button>
    </div>

    @if (!$this->hasHeartbeat)
        <flux:callout variant="warning" class="mt-6">
            <flux:callout.heading>{{ __('Sin señal de heartbeat') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Aún no hay un heartbeat registrado. Activa los workers (abajo) y el scheduler; la página detectará liveness en el primer minuto.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->status['health'] === 'ok')
        <flux:callout variant="success" class="mt-6">
            <flux:callout.heading>{{ __('Sistema saludable') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Worker activo y sin backlog estancado.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->status['health'] === 'warning')
        <flux:callout variant="warning" class="mt-6">
            <flux:callout.heading>{{ __('Backlog detectado') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Hay trabajos pendientes sin procesar. Revisa la cola y los fallos.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:callout variant="danger" class="mt-6">
            <flux:callout.heading>{{ __('Workers caídos') }}</flux:callout.heading>
            <flux:callout.text>{{ __('El heartbeat está viejo: el worker o el scheduler no están corriendo. Actívalos con los comandos de abajo.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
        <flux:card class="mt-4">
            <flux:heading size="text-lg">{{ __('Estado general') }}</flux:heading>
            <dl class="mt-4 grid grid-cols-1 gap-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-500 dark:text-zinc-400">Worker</dt>
                    <dd>
                        @if ($this->status['worker_up'])
                            <flux:badge color="green" icon="check-circle">{{ __('Activo') }}</flux:badge>
                        @else
                            <flux:badge color="red" icon="x-circle">{{ __('Caído') }}</flux:badge>
                        @endif
                    </dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-500 dark:text-zinc-400">Último heartbeat</dt>
                    <dd>
                        @if ($this->status['last_heartbeat_at'])
                            {{ ($this->status['seconds_since_heartbeat']) }}s
                        @else
                            {{ __('Sin registro') }}
                        @endif
                    </dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-500 dark:text-zinc-400">Fallos</dt>
                    <dd>{{ $this->failedTotal }}</dd>
                </div>
            </dl>
        </flux:card>

        <flux:card class="mt-4">
            <flux:heading size="text-lg">{{ __('Colas') }}</flux:heading>
            <table class="w-full text-sm">
                <thead class="text-left text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-2 py-2">Cola</th>
                        <th class="px-2 py-2">Pendientes</th>
                        <th class="px-2 py-2">Retrasadas</th>
                        <th class="px-2 py-2">Procesando</th>
                        <th class="px-2 py-2">Más antigua</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($this->status['queues'] as $queue)
                        <tr>
                            <td class="px-2 py-2">
                                <flux:badge :color="$this->queueBadgeColor($queue['name'])" variant="outline">{{ $queue['name'] }}</flux:badge>
                            </td>
                            <td class="px-2 py-2">{{ $queue['pending'] }}</td>
                            <td class="px-2 py-2">{{ $queue['delayed'] }}</td>
                            <td class="px-2 py-2">{{ $queue['reserved'] }}</td>
                            <td class="px-2 py-2">
                                @if ($queue['oldest_pending_age'] !== null)
                                    @if ($queue['oldest_pending_stale'])
                                        <flux:badge color="red">{{ $queue['oldest_pending_age'] }}s</flux:badge>
                                    @else
                                        {{ $queue['oldest_pending_age'] }}s
                                    @endif
                                @else
                                    <span class="text-zinc-400 dark:text-zinc-500">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </flux:card>
    </div>

    <div class="mt-6">
        <flux:card class="mt-4">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="text-lg">{{ __('Trabajos fallidos') }} ({{ $this->failedTotal }})</flux:heading>
                @if ($this->failedTotal > 0)
                    <flux:button
                        variant="danger"
                        wire:click="flushFailed"
                        wire:confirm="{{ __('¿Limpiar todos los trabajos fallidos?') }}"
                    >{{ __('Limpiar') }}</flux:button>
                @endif
            </div>

            @if (count($this->failed) === 0)
                <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No hay trabajos fallidos.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-2 py-2">Clase</th>
                            <th class="px-2 py-2">Cola</th>
                            <th class="px-2 py-2">Con. / Falló</th>
                            <th class="px-2 py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->failed as $job)
                            <tr>
                                <td class="px-2 py-2 font-mono text-xs">{{ $job['class'] ?? $job['id'] }}</td>
                                <td class="px-2 py-2">{{ $job['queue'] }}</td>
                                <td class="px-2 py-2">
                                    @if ($job['failed_at'])
                                        <span class="text-zinc-500 dark:text-zinc-400">{{ $job['failed_at'] }}</span>
                                    @endif
                                    @if ($job['exception'])
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate max-w-xs">{{ $job['exception'] }}</p>
                                    @endif
                                </td>
                                <td class="px-2 py-2">
                                    <div class="flex gap-2">
                                        <flux:button variant="primary" size="sm" wire:click="retry('{{ $job['id'] }}')">Reintentar</flux:button>
                                        <flux:button variant="outline" size="sm" wire:click="forget('{{ $job['id'] }}')">Descartar</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </flux:card>
    </div>

    <div class="mt-6">
        <flux:card class="mt-4">
            <flux:heading size="text-lg">{{ __('Cómo activar los workers') }}</flux:heading>
            <flux:text>{{ __('Ejecuta ambos procesos en el servidor (o súmelos al monitor de procesos). Entorno actual: ') }}<b>{{ $this->activation['env'] }}</b></flux:text>

            <div class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg px-3 py-2 font-mono text-xs">
                    <span class="truncate">{{ $this->activation['queue_work'] }}</span>
                    <flux:button variant="outline" size="sm" x-on:click="navigator.clipboard.writeText($el.previousElementSibling.innerText)">Copiar</flux:button>
                </div>
                <div class="flex items-center justify-between gap-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg px-3 py-2 font-mono text-xs">
                    <span class="truncate">{{ $this->activation['schedule_work'] }}</span>
                    <flux:button variant="outline" size="sm" x-on:click="navigator.clipboard.writeText($el.previousElementSibling.innerText)">Copiar</flux:button>
                </div>
            </div>

            <flux:callout variant="info" class="mt-4">
                <flux:callout.heading>{{ __('Monitoreo proactivo') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Opcional: agenda el monitor y recibe una alerta cuando una cola supere su umbral:') }}
                    <code class="font-mono text-xs">{{ $this->activation['monitor'] }}</code>
                </flux:callout.text>
            </flux:callout>

            <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">{{ $this->activation['supervisor_note'] }}</p>
        </flux:card>
    </div>
</div>