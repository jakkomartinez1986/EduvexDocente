<?php

declare(strict_types=1);

use App\Models\Security\Authorizations\Permission;
use App\Models\Security\Authorizations\Role;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Crear Rol')] class extends Component {
    public string $name = '';
    public string $description = '';
    public array $selectedPermissions = [];

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'selectedPermissions' => ['required', 'array', 'min:1'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripcion',
            'selectedPermissions' => 'permisos',
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function permissionGroups(): array
    {
        $modules = Permission::select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->toArray();

        $grouped = [];
        foreach ($modules as $module) {
            $grouped[$module] = Permission::where('module', $module)
                ->orderBy('name')
                ->get(['id', 'name', 'label']);
        }

        return $grouped;
    }

    public function save(): void
    {
        $this->validate();

        $role = Role::create([
            'name' => $this->name,
            'description' => $this->description,
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($this->selectedPermissions);

        Flux::toast(variant: 'success', text: __('Rol creado correctamente.'));
        $this->redirect(route('admin.roles.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Crear Rol') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Registrar un nuevo rol en el sistema') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.roles.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.roles.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Roles') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Crear') }}</span>
    </nav>

    <form wire:submit="save" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion del Rol') }}</flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Nombre') }} *</flux:label>
                    <flux:input wire:model="name" placeholder="EJ: DOCENTE" />
                    @error('name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Descripcion') }}</flux:label>
                    <flux:input wire:model="description" placeholder="{{ __('Descripcion breve del rol') }}" />
                </flux:field>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Permisos') }}</flux:heading>
            <flux:text variant="subtle" class="mb-4 text-xs">{{ __('Seleccione los permisos que tendra este rol.') }}</flux:text>
            @error('selectedPermissions') <flux:description class="text-red-500 mb-2">{{ $message }}</flux:description> @enderror

            @forelse ($this->permissionGroups as $module => $permissions)
                <div class="mb-4">
                    <flux:heading size="sm" class="mb-2">{{ $module }}</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($permissions as $permission)
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition has-[:checked]:bg-blue-50 has-[:checked]:border-blue-300 has-[:checked]:dark:bg-blue-900/20 has-[:checked]:dark:border-blue-700">
                                <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}" class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $permission->label ?? $permission->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @empty
                <flux:text variant="subtle" class="text-sm">{{ __('No hay permisos disponibles.') }}</flux:text>
            @endforelse
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Guardar Rol') }}</flux:button>
            <flux:button href="{{ route('admin.roles.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
