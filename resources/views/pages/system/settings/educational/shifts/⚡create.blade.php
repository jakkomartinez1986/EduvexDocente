<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\Shift;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Crear Jornada')] class extends Component {
    public string $shift_name = '';

    protected function rules(): array
    {
        return [
            'shift_name' => ['required', 'string', 'max:100', 'unique:shifts,shift_name'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'shift_name' => 'nombre de la jornada',
        ];
    }

    public function save(): void
    {
        $this->validate();

        Shift::create([
            'shift_name' => $this->shift_name,
            'status' => 1,
        ]);

        Flux::toast(variant: 'success', text: __('Jornada creada correctamente.'));
        $this->redirect(route('admin.settings.shifts.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Crear Jornada') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Registrar una nueva jornada academica') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.settings.shifts.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.shifts.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Jornadas') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Crear') }}</span>
    </nav>

    <form wire:submit="save" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion de la Jornada') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Nombre') }} *</flux:label>
                <flux:input wire:model="shift_name" placeholder="EJ: MATUTINA" />
                @error('shift_name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
            </flux:field>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Guardar Jornada') }}</flux:button>
            <flux:button href="{{ route('admin.settings.shifts.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
