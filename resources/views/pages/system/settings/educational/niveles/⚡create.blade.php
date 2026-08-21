<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\Shift;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Crear Nivel')] class extends Component {

    public string $nivel_name = '';

    public int $shift_id = 0;

    public function getShiftsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return Shift::where('status', 1)->orderBy('shift_name')->get();
    }

    protected function rules(): array
    {
        return [
            'nivel_name' => ['required', 'string', 'max:100', 'unique:nivels,nivel_name'],
            'shift_id' => ['required', 'exists:shifts,id'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'nivel_name' => 'nombre del nivel',
            'shift_id' => 'turno',
        ];
    }

    public function save(): void
    {
        $this->validate();

        Nivel::create([
            'nivel_name' => $this->nivel_name,
            'shift_id' => $this->shift_id,
        ]);

        Flux::toast(variant: 'success', text: __('Nivel creado correctamente.'));
        $this->redirect(route('admin.settings.niveles.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Crear Nivel') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Registrar un nuevo nivel educativo') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.settings.niveles.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.niveles.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Niveles') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Crear') }}</span>
    </nav>

    <form wire:submit="save" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion del Nivel') }}</flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Nombre') }} *</flux:label>
                    <flux:input wire:model="nivel_name" placeholder="EJ: PRIMERO" />
                    @error('nivel_name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Turno') }} *</flux:label>
                    <flux:select wire:model="shift_id" placeholder="{{ __('Seleccione un turno') }}">
                        @foreach ($this->shifts as $shift)
                            <flux:select.option value="{{ $shift->id }}">{{ $shift->shift_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('shift_id') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Guardar Nivel') }}</flux:button>
            <flux:button href="{{ route('admin.settings.niveles.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
