<?php

use App\Models\User;
use App\Services\Queue\WorkerMonitorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Cache::flush());

function queueWorkerAdminUser(): User
{
    $role = Role::firstOrCreate(
        ['name' => 'SUPER-ADMIN', 'guard_name' => 'web'],
        ['description' => 'Super Administrador'],
    );

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function logQueueFailure(string $message): string
{
    $id = (string) Str::uuid();

    app('queue.failer')->log('database', 'default', json_encode([
        'uuid' => $id,
        'displayName' => 'App\Jobs\QueueHeartbeat',
        'job' => 'App\Jobs\QueueHeartbeat',
    ]), $message);

    return $id;
}

it('renderiza la página de monitoreo de workers sin fallos', function (): void {
    Livewire::actingAs(queueWorkerAdminUser())
        ->test('pages::system.settings.queue.workers.index')
        ->assertOk()
        ->assertSee('Monitoreo de Trabajadores');
});

it('reporta worker caído cuando no hay heartbeat registrado', function (): void {
    Livewire::actingAs(queueWorkerAdminUser())
        ->test('pages::system.settings.queue.workers.index')
        ->assertOk()
        ->assertSet('hasHeartbeat', false)
        ->assertSet('status.worker_up', false);
});

it('marca el worker como activo cuando existe un heartbeat fresco', function (): void {
    Cache::put(WorkerMonitorService::HEARTBEAT_KEY, now()->getTimestamp(), 600);

    Livewire::actingAs(queueWorkerAdminUser())
        ->test('pages::system.settings.queue.workers.index')
        ->assertOk()
        ->assertSet('hasHeartbeat', true)
        ->assertSet('status.worker_up', true);
});

it('reenvía un trabajo fallido desde la página', function (): void {
    $id = logQueueFailure('TestException: retry from page');

    Livewire::actingAs(queueWorkerAdminUser())
        ->test('pages::system.settings.queue.workers.index')
        ->call('retry', $id)
        ->assertHasNoErrors()
        ->assertSet('failedTotal', 0);
});
