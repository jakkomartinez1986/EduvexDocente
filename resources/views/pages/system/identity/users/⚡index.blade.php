<?php
declare(strict_types=1);

use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;


new #[Title('Usuarios')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 15;
    public string $sortField = 'id';
    public string $sortDirection = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function confirmToggle(int $id): void
    {
        $this->dispatch('showConfirm',
            message: 'Esta seguro de cambiar el estado de este usuario?',
            eventName: 'execute-toggle-user',
            eventParams: ['id' => $id]
        )->to('confirm-action');
    }

    #[On('execute-toggle-user')]
    public function executeToggleUser(array $params): void
    {
        $user = User::find($params['id']);
        if ($user) {
            $this->toggleStatus($user);
        }
    }

    public function getRecordsProperty()
    {
        $managementRoles = ['DECE', 'INSPECTOR', 'RECTOR', 'VICERRECTOR','SUPER-ADMIN','ADMIN'];

        return User::query()
            ->with(['roles'])
            ->where(function ($q) use ($managementRoles) {
                $q->doesntHave('roles')
                    ->orWhereHas('roles', fn ($r) => $r->whereIn('name', $managementRoles));
            })
            ->when($this->search, fn ($q) =>
                $q->where(function ($q2) {
                    $q2->where('name', 'like', "%{$this->search}%")
                        ->orWhere('lastname', 'like', "%{$this->search}%")
                        ->orWhere('dni', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                })
            )
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function toggleStatus(User $user): void
    {
        $user->status = $user->status === 1 ? 0 : 1;
        $user->save();

        Flux::toast(
            variant: 'success',
            text: "Usuario {$user->fullname} " . ($user->status === 1 ? 'activado' : 'desactivado') . ' correctamente.'
        );
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Usuarios') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Usuarios sin rol asignado y personal de gestion (DECE, Admin, Super-Admmin, Inspector, Rector, Vicerrector)') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('system.identity.users.create') }}" wire:navigate variant="primary">
                <flux:icon.plus /> {{ __('Nuevo Usuario') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Usuarios') }}</span>
    </nav>

    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-6">
        <div class="w-full sm:w-96">
            <flux:input wire:model.live.debounce="search" :placeholder="__('Buscar por nombre, apellido, DNI o email...')" icon="magnifying-glass" />
        </div>
    </div>

    <div>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100" wire:click="sortBy('lastname')">
                            {{ __('Nombre') }}
                            @if($this->sortField === 'lastname' && $this->sortDirection === 'asc') <flux:icon.chevron-up class="size-3 inline" /> @elseif($this->sortField === 'lastname') <flux:icon.chevron-down class="size-3 inline" /> @endif
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100" wire:click="sortBy('dni')">
                            {{ __('DNI') }}
                            @if($this->sortField === 'dni' && $this->sortDirection === 'asc') <flux:icon.chevron-up class="size-3 inline" /> @elseif($this->sortField === 'dni') <flux:icon.chevron-down class="size-3 inline" /> @endif
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Email') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Telefono') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estado') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Roles') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->records as $user)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <flux:avatar src="{{ $user->defaultUserPhotoUrl() }}" size="size-8" />
                                    <div>
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->fullname }}</div>
                                        <div class="text-xs text-zinc-500">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $user->dni ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $user->cellphone ?? $user->phone ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="confirmToggle({{ $user->id }})"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium cursor-pointer transition
                                            {{ $user->status === 1
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 hover:bg-emerald-100'
                                                : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-100' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $user->status === 1 ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                    {{ $user->status === 1 ? __('Activo') : __('Inactivo') }}
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->roles as $role)
                                        <flux:badge size="xs" color="blue" variant="outline">{{ $role->name }}</flux:badge>
                                    @empty
                                        <flux:text variant="subtle" class="text-xs">{{ __('Sin roles') }}</flux:text>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item href="{{ route('system.identity.users.show', $user->id) }}" wire:navigate icon="eye">{{ __('Ver') }}</flux:menu.item>
                                        <flux:menu.item href="{{ route('system.identity.users.edit', $user->id) }}" wire:navigate icon="pencil">{{ __('Editar') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <flux:icon.user class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron usuarios.') }}</flux:text>
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