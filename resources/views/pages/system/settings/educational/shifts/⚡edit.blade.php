<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\Shift;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Jornada')] class extends Component {
    public ?Shift $shift = null;
    public string $shift_name = '';

    public function mount(int $id): void
    {
        $this->shift = Shift::findOrFail($id);

        $this->fill([
            'shift_name' => $this->shift->shift_name,
        ]);
    }

    protected function rules(): array
    {
        return [
            'shift_name' => ['required', 'string', 'max:100', 'unique:shifts,shift_name,' . $this->shift->id],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'shift_name' => 'nombre de la jornada',
        ];
    }

    public function update(): void
    {
        $this->validate();

        $this->shift->update([
            'shift_name' => $this->shift_name,
        ]);

        Flux::toast(variant: 'success', text: __('Jornada actualizada correctamente.'));
        $this->redirect(route('admin.settings.shifts.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Editar Jornada') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Actualizar informacion de la jornada') }}</flux:text>
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
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Editar') }}</span>
    </nav>

    <form wire:submit="update" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion de la Jornada') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Nombre') }} *</flux:label>
                <flux:input wire:model="shift_name" />
                @error('shift_name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
            </flux:field>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Actualizar Jornada') }}</flux:button>
            <flux:button href="{{ route('admin.settings.shifts.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
