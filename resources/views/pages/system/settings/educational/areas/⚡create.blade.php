<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\Area;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Crear Area')] class extends Component {
    public string $area_name = '';

    protected function rules(): array
    {
        return [
            'area_name' => ['required', 'string', 'max:100', 'unique:areas,area_name'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'area_name' => 'nombre del area',
        ];
    }

    public function save(): void
    {
        $this->validate();

        Area::create([
            'area_name' => $this->area_name,
        ]);

        Flux::toast(variant: 'success', text: __('Area creada correctamente.'));
        $this->redirect(route('admin.settings.areas.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Crear Area') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Registrar una nueva area academica') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.settings.areas.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.areas.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Areas') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Crear') }}</span>
    </nav>

    <form wire:submit="save" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion del Area') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Nombre') }} *</flux:label>
                <flux:input wire:model="area_name" placeholder="EJ: CIENCIAS NATURALES" />
                @error('area_name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
            </flux:field>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Guardar Area') }}</flux:button>
            <flux:button href="{{ route('admin.settings.areas.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
