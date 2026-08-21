<?php

declare(strict_types=1);

use App\Models\Setting\YearSettings\ScolarYear;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Año Escolar')] class extends Component {
    public ?ScolarYear $year = null;
    public string $year_name = '';
    public ?string $start_date = null;
    public ?string $end_date = null;

    public function mount(int $id): void
    {
        $this->year = ScolarYear::findOrFail($id);

        $this->fill([
            'year_name' => $this->year->year_name,
            'start_date' => $this->year->start_date?->format('Y-m-d'),
            'end_date' => $this->year->end_date?->format('Y-m-d'),
        ]);
    }

    protected function rules(): array
    {
        return [
            'year_name' => ['required', 'string', 'max:20', 'unique:scolar_years,year_name,' . $this->year->id],
            'start_date' => ['required', 'date', 'before:end_date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'year_name' => 'nombre del año',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de fin',
        ];
    }

    public function update(): void
    {
        $this->validate();

        $this->year->update([
            'year_name' => $this->year_name,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ]);

        Flux::toast(variant: 'success', text: __('Año escolar actualizado correctamente.'));
        $this->redirect(route('admin.settings.years.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Editar Año Escolar') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Actualizar informacion del año escolar') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.settings.years.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.years.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Años Escolares') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Editar') }}</span>
    </nav>

    <form wire:submit="update" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion del Año Escolar') }}</flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Nombre') }} *</flux:label>
                    <flux:input wire:model="year_name" />
                    @error('year_name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <div></div>
                <flux:field>
                    <flux:label>{{ __('Fecha de Inicio') }} *</flux:label>
                    <flux:input wire:model="start_date" type="date" />
                    @error('start_date') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Fecha de Fin') }} *</flux:label>
                    <flux:input wire:model="end_date" type="date" />
                    @error('end_date') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Actualizar Año Escolar') }}</flux:button>
            <flux:button href="{{ route('admin.settings.years.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
