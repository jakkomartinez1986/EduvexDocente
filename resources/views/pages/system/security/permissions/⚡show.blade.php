<?php

declare(strict_types=1);

use App\Models\Security\Authorizations\Permission;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalle de Permiso')] class extends Component {
    public int $permissionId;

    public function mount(int $id): void
    {
        $this->permissionId = $id;
    }

    public function getPermissionProperty(): Permission
    {
        return Permission::query()
            ->with(['roles'])
            ->withCount('roles')
            ->findOrFail($this->permissionId);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Detalle de Permiso') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Informacion completa del permiso') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('admin.permissions.edit', $this->permissionId) }}" wire:navigate variant="primary">
                <flux:icon.pencil /> {{ __('Editar') }}
            </flux:button>
            <flux:button href="{{ route('admin.permissions.index') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.permissions.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Permisos') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $this->permission->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                <div class="mx-auto mb-4 flex size-20 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-2xl font-bold">
                    {{ strtoupper(substr($this->permission->name, 0, 2)) }}
                </div>
                <flux:heading size="lg">{{ $this->permission->name }}</flux:heading>
                @if ($this->permission->label)
                    <flux:text class="text-zinc-500 mt-1">{{ $this->permission->label }}</flux:text>
                @endif
                <div class="mt-3">
                    <flux:badge color="blue">{{ $this->permission->module }}</flux:badge>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion del Permiso') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nombre') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->permission->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Etiqueta') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->permission->label ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Modulo') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->permission->module }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Guard') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->permission->guard_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Creacion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->permission->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Roles Asignados') }}</flux:heading>
                @forelse ($this->permission->roles as $role)
                    <div class="flex items-center gap-3 py-2 {{ !$loop->last ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                        <div class="flex size-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 text-xs font-bold shrink-0">
                            {{ strtoupper(substr($role->name, 0, 2)) }}
                        </div>
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $role->name }}</div>
                            @if ($role->description)
                                <div class="text-xs text-zinc-500">{{ $role->description }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <flux:icon.shield-check class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text variant="subtle" class="text-sm">{{ __('Este permiso no esta asignado a ningun rol.') }}</flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
