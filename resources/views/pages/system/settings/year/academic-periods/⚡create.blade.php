<?php

declare(strict_types=1);

use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Crear Periodo Academico')] class extends Component {
    public string $trimester_name = '';
    public int $year_id = 0;
    public string $start_date = '';
    public string $end_date = '';
    public ?string $grading_open_date = null;
    public ?string $grading_close_date = null;
    public bool $is_supletorio = false;

    public function getYearsProperty()
    {
        return ScolarYear::query()
            ->where('status', 1)
            ->orderBy('year_name')
            ->get();
    }

    protected function rules(): array
    {
        return [
            'trimester_name' => ['required', 'string', 'max:50'],
            'year_id' => ['required', 'exists:scolar_years,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'grading_open_date' => ['nullable', 'date'],
            'grading_close_date' => ['nullable', 'date', 'nullable_after_or_equal:grading_open_date'],
            'is_supletorio' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'trimester_name' => 'nombre del trimestre',
            'year_id' => 'ano escolar',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de fin',
            'grading_open_date' => 'apertura de calificaciones',
            'grading_close_date' => 'cierre de calificaciones',
            'is_supletorio' => 'es supletorio',
        ];
    }

    public function save(): void
    {
        $this->validate();

        AcademicPeriod::create([
            'trimester_name' => $this->trimester_name,
            'year_id' => $this->year_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'grading_open_date' => $this->grading_open_date ?: null,
            'grading_close_date' => $this->grading_close_date ?: null,
            'is_supletorio' => $this->is_supletorio,
        ]);

        Flux::toast(variant: 'success', text: __('Periodo academico creado correctamente.'));
        $this->redirect(route('admin.settings.trimesters.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Crear Periodo Academico') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Registrar un nuevo periodo academico') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.settings.trimesters.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.trimesters.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Periodos Academicos') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Crear') }}</span>
    </nav>

    <form wire:submit="save" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion del Periodo') }}</flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Nombre del Trimestre') }} *</flux:label>
                    <flux:input wire:model="trimester_name" placeholder="EJ: PRIMER TRIMESTRE" />
                    @error('trimester_name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Ano Escolar') }} *</flux:label>
                    <flux:select wire:model="year_id" placeholder="{{ __('Seleccione un ano escolar') }}">
                        @foreach ($this->years as $year)
                            <flux:select.option value="{{ $year->id }}">{{ $year->year_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('year_id') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Ventana de Calificaciones') }}</flux:heading>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-4">{{ __('Defina el periodo en el que los docentes podran ingresar calificaciones. Deje vacio si no aplica.') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Apertura de Calificaciones') }}</flux:label>
                    <flux:input wire:model="grading_open_date" type="date" />
                    @error('grading_open_date') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Cierre de Calificaciones') }}</flux:label>
                    <flux:input wire:model="grading_close_date" type="date" />
                    @error('grading_close_date') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
            </div>
            <div class="mt-4">
                <flux:field>
                    <flux:checkbox wire:model="is_supletorio" label="{{ __('Este periodo es de Supletorio') }}" />
                </flux:field>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Guardar Periodo') }}</flux:button>
            <flux:button href="{{ route('admin.settings.trimesters.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>