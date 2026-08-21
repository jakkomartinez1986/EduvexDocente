<?php

declare(strict_types=1);

use App\Models\Security\Authorizations\Role;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Roles')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 15;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDestroy(int $id): void
    {
        $this->dispatch('showConfirm',
            message: 'Esta seguro de eliminar este rol?',
            eventName: 'execute-destroy-role',
            eventParams: ['id' => $id]
        )->to('confirm-action');
    }

    #[On('execute-destroy-role')]
    public function executeDestroyRole(array $params): void
    {
        $role = Role::find($params['id']);
        if ($role) {
            $this->destroy($role);
        }
    }

    public function getRecordsProperty()
    {
        return Role::query()
            ->withCount(['permissions'])
            ->when($this->search, fn ($q) =>
                $q->where('name', 'ilike', "%{$this->search}%")
                    ->orWhere('description', 'ilike', "%{$this->search}%")
            )
            ->latest()
            ->paginate($this->perPage);
    }

    public function destroy(Role $role): void
    {
        if ($role->name === 'SUPER-ADMIN') {
            Flux::toast(variant: 'error', text: __('No se puede eliminar el rol Super Admin.'));
            return;
        }

        $role->delete();

        Flux::toast(variant: 'success', text: __('Rol eliminado correctamente.'));
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Roles') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Gestion de roles y permisos del sistema') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('admin.roles.create') }}" wire:navigate variant="primary">
                <flux:icon.plus /> {{ __('Nuevo Rol') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Roles') }}</span>
    </nav>

    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-6">
        <div class="w-full sm:w-96">
            <flux:input wire:model.live.debounce="search" :placeholder="__('Buscar por nombre o descripcion...')" icon="magnifying-glass" />
        </div>
    </div>

    <div>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Nombre') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Descripcion') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Permisos') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->records as $role)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 text-xs font-bold shrink-0">
                                        {{ strtoupper(substr($role->name, 0, 2)) }}
                                    </div>
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $role->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $role->description ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" color="blue">{{ $role->permissions_count }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item href="{{ route('admin.roles.show', $role->id) }}" wire:navigate icon="eye">{{ __('Ver') }}</flux:menu.item>
                                        <flux:menu.item href="{{ route('admin.roles.edit', $role->id) }}" wire:navigate icon="pencil">{{ __('Editar') }}</flux:menu.item>
                                        <flux:menu.item wire:click="confirmDestroy({{ $role->id }})" icon="trash" class="text-red-600">{{ __('Eliminar') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-16 text-center">
                                <flux:icon.lock-closed class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron roles.') }}</flux:text>
                                <flux:text variant="subtle" class="text-xs mt-1">{{ __('Intente con otros terminos de busqueda.') }}</flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $this->records->links() }}</div>
    </div>

    <livewire:confirm-action />
</div>
