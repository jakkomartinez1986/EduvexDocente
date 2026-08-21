<?php

declare(strict_types=1);

use App\Models\Security\Authorizations\Role;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalle de Rol')] class extends Component {
    public int $roleId;

    public function mount(int $id): void
    {
        $this->roleId = $id;
    }

    public function getRoleProperty(): Role
    {
        return Role::query()
            ->with(['permissions'])
            ->withCount('permissions')
            ->findOrFail($this->roleId);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Detalle de Rol') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Informacion completa del rol') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('admin.roles.edit', $this->roleId) }}" wire:navigate variant="primary">
                <flux:icon.pencil /> {{ __('Editar') }}
            </flux:button>
            <flux:button href="{{ route('admin.roles.index') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.roles.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Roles') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $this->role->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                <div class="mx-auto mb-4 flex size-20 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-2xl font-bold">
                    {{ strtoupper(substr($this->role->name, 0, 2)) }}
                </div>
                <flux:heading size="lg">{{ $this->role->name }}</flux:heading>
                @if ($this->role->description)
                    <flux:text class="text-zinc-500 mt-1">{{ $this->role->description }}</flux:text>
                @endif
                <div class="mt-3">
                    <flux:badge color="blue">{{ $this->role->permissions_count }} {{ __('permisos') }}</flux:badge>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion del Rol') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nombre') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->role->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Descripcion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->role->description ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Guard') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->role->guard_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Creacion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->role->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Permisos Asignados') }}</flux:heading>
                @forelse ($this->role->permissions->groupBy('module') as $module => $permissions)
                    <div class="mb-4 last:mb-0">
                        <flux:heading size="sm" class="mb-2">{{ $module }}</flux:heading>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($permissions as $permission)
                                <flux:badge size="sm" color="green" variant="outline">{{ $permission->label ?? $permission->name }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <flux:icon.key class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text variant="subtle" class="text-sm">{{ __('Este rol no tiene permisos asignados.') }}</flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
