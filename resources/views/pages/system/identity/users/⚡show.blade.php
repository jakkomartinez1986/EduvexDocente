<?php
declare(strict_types=1);

use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalle de Usuario')] class extends Component {
    public int $userId;

    public function mount(int $id): void
    {
        $this->userId = $id;
    }

    public function getUserProperty(): User
    {
        return User::query()
            ->with(['roles'])
            ->findOrFail($this->userId);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Detalle de Usuario') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Informacion completa del usuario') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('system.identity.users.edit', $this->userId) }}" wire:navigate variant="primary">
                <flux:icon.pencil /> {{ __('Editar') }}
            </flux:button>
            <flux:button href="{{ route('system.identity.users.index') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('system.identity.users.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Usuarios') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $this->user->fullname }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                <flux:avatar src="{{ $this->user->defaultUserPhotoUrl() }}" size="size-20" class="mx-auto mb-4" />
                <flux:heading size="lg">{{ $this->user->fullname }}</flux:heading>
                <flux:text class="text-zinc-500">{{ $this->user->email }}</flux:text>
                <div class="mt-3">
                    <flux:badge color="{{ $this->user->status === 1 ? 'green' : 'red' }}">
                        {{ $this->user->status === 1 ? __('Activo') : __('Inactivo') }}
                    </flux:badge>
                </div>
                @if ($this->user->roles->count())
                    <div class="mt-3 flex flex-wrap gap-1 justify-center">
                        @foreach ($this->user->roles as $role)
                            <flux:badge size="xs" color="blue" variant="outline">{{ $role->name }}</flux:badge>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion Personal') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nombre') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->user->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Apellido') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->user->lastname ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('DNI') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->user->dni ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Email') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->user->email ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Telefono') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->user->phone ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Celular') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->user->cellphone ?? '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Direccion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->user->address ?? '-' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Sistema') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Estado') }}</dt>
                        <dd class="mt-1">
                            <flux:badge color="{{ $this->user->status === 1 ? 'green' : 'red' }}">
                                {{ $this->user->status === 1 ? __('Activo') : __('Inactivo') }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Debe Cambiar Contrasena') }}</dt>
                        <dd class="mt-1">
                            <flux:badge color="{{ $this->user->must_change_password ? 'amber' : 'zinc' }}">
                                {{ $this->user->must_change_password ? __('Si') : __('No') }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Email Verificado') }}</dt>
                        <dd class="mt-1">
                            <flux:badge color="{{ $this->user->email_verified_at ? 'green' : 'amber' }}">
                                {{ $this->user->email_verified_at ? __('Verificado') : __('Pendiente') }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Creacion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->user->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>