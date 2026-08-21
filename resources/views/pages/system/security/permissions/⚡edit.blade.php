<?php

declare(strict_types=1);

use App\Models\Security\Authorizations\Permission;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Permiso')] class extends Component {
    public ?Permission $permission = null;
    public string $name = '';
    public string $label = '';
    public string $module = '';

    public function mount(int $id): void
    {
        $this->permission = Permission::findOrFail($id);

        $this->fill([
            'name' => $this->permission->name,
            'label' => $this->permission->label ?? '',
            'module' => $this->permission->module,
        ]);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name,' . $this->permission->id . ',guard_name,' . $this->permission->guard_name],
            'label' => ['required', 'string', 'max:255'],
            'module' => ['required', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'label' => 'etiqueta',
            'module' => 'modulo',
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function existingModules(): array
    {
        return Permission::select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->toArray();
    }

    public function update(): void
    {
        $this->validate();

        $this->permission->update([
            'name' => $this->name,
            'label' => $this->label,
            'module' => $this->module,
        ]);

        Flux::toast(variant: 'success', text: __('Permiso actualizado correctamente.'));
        $this->redirect(route('admin.permissions.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Editar Permiso') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Actualizar informacion del permiso') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.permissions.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.permissions.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Permisos') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Editar') }}</span>
    </nav>

    <form wire:submit="update" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion del Permiso') }}</flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Nombre') }} *</flux:label>
                    <flux:input wire:model="name" />
                    @error('name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Etiqueta') }} *</flux:label>
                    <flux:input wire:model="label" />
                    @error('label') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Modulo') }} *</flux:label>
                <flux:input wire:model="module" list="modules-list" />
                @error('module') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                <datalist id="modules-list">
                    @foreach ($this->existingModules as $module)
                        <option value="{{ $module }}">
                    @endforeach
                </datalist>
            </flux:field>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Actualizar Permiso') }}</flux:button>
            <flux:button href="{{ route('admin.permissions.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
