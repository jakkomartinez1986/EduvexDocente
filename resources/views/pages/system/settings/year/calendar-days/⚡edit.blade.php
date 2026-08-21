<?php

declare(strict_types=1);

use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\Setting\YearSettings\ScolarYear;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Dia de Calendario')] class extends Component {
    public ?CalendarDay $calendarDay = null;
    public int $year_id = 0;
    public ?int $trimester_id = null;
    public ?string $period = null;
    public string $date = '';
    public ?string $month_name = null;
    public string $day_name = '';
    public ?int $week = null;
    public ?int $day_number = null;
    public ?string $activity = null;
    public bool $is_holiday = false;

    public function mount(int $id): void
    {
        $this->calendarDay = CalendarDay::findOrFail($id);

        $this->fill([
            'year_id' => $this->calendarDay->year_id,
            'trimester_id' => $this->calendarDay->trimester_id,
            'period' => $this->calendarDay->period,
            'date' => $this->calendarDay->date?->format('Y-m-d') ?? '',
            'month_name' => $this->calendarDay->month_name,
            'day_name' => $this->calendarDay->day_name,
            'week' => $this->calendarDay->week,
            'day_number' => $this->calendarDay->day_number,
            'activity' => $this->calendarDay->activity,
            'is_holiday' => $this->calendarDay->is_holiday,
        ]);
    }

    public function getYearsProperty()
    {
        return ScolarYear::query()
            ->where('status', 1)
            ->orderByDesc('year_name')
            ->get();
    }

    public function getTrimestersProperty()
    {
        if (! $this->year_id) {
            return collect();
        }

        return AcademicPeriod::query()
            ->where('year_id', $this->year_id)
            ->where('status', 1)
            ->orderBy('trimester_name')
            ->get();
    }

    protected function rules(): array
    {
        return [
            'year_id' => ['required', 'exists:scolar_years,id'],
            'trimester_id' => ['nullable', 'exists:academic_periods,id'],
            'period' => ['nullable', 'string', 'max:20'],
            'date' => ['required', 'date', 'date_format:Y-m-d', 'unique:calendar_days,date,' . $this->calendarDay->id . ',id,year_id,' . $this->year_id],
            'month_name' => ['nullable', 'string', 'max:20'],
            'day_name' => ['required', 'string', 'max:20'],
            'week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'day_number' => ['nullable', 'integer', 'min:1', 'max:31'],
            'activity' => ['nullable', 'string', 'max:255'],
            'is_holiday' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'year_id' => 'ano escolar',
            'trimester_id' => 'trimestre',
            'period' => 'periodo',
            'date' => 'fecha',
            'month_name' => 'nombre del mes',
            'day_name' => 'nombre del dia',
            'week' => 'semana',
            'day_number' => 'numero del dia',
            'activity' => 'actividad',
            'is_holiday' => 'feriado',
        ];
    }

    public function updatedYearId(): void
    {
        $this->trimester_id = null;
    }

    public function updatedDate(): void
    {
        if ($this->date) {
            $carbon = \Illuminate\Support\Carbon::parse($this->date);

            $this->month_name = $carbon->translatedFormat('F');
            $this->day_name = $carbon->translatedFormat('l');
            $this->day_number = (int) $carbon->format('d');
        }
    }

    public function update(): void
    {
        $this->validate();

        $this->calendarDay->update([
            'year_id' => $this->year_id,
            'trimester_id' => $this->trimester_id,
            'period' => $this->period,
            'date' => $this->date,
            'month_name' => $this->month_name,
            'day_name' => $this->day_name,
            'week' => $this->week,
            'day_number' => $this->day_number,
            'activity' => $this->activity,
            'is_holiday' => $this->is_holiday,
        ]);

        Flux::toast(variant: 'success', text: __('Dia de calendario actualizado correctamente.'));
        $this->redirect(route('admin.settings.calendar-scolars.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Editar Dia de Calendario') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Actualizar informacion del dia') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.settings.calendar-scolars.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.calendar-scolars.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Calendario Escolar') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Editar') }}</span>
    </nav>

    <form wire:submit="update" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion del Dia') }}</flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Ano Escolar') }} *</flux:label>
                    <flux:select wire:model="year_id" placeholder="{{ __('Seleccione un ano') }}">
                        @foreach ($this->years as $year)
                            <flux:select.option value="{{ $year->id }}">{{ $year->year_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('year_id') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Trimestre') }}</flux:label>
                    <flux:select wire:model="trimester_id" placeholder="{{ __('Seleccione un trimestre') }}">
                        @foreach ($this->trimesters as $trimester)
                            <flux:select.option value="{{ $trimester->id }}">{{ $trimester->trimester_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('trimester_id') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <flux:field>
                    <flux:label>{{ __('Fecha') }} *</flux:label>
                    <flux:input type="date" wire:model="date" />
                    @error('date') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Periodo') }}</flux:label>
                    <flux:input wire:model="period" placeholder="EJ: 1ER TRIMESTRE" />
                    @error('period') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                <flux:field>
                    <flux:label>{{ __('Mes') }}</flux:label>
                    <flux:input wire:model="month_name" placeholder="EJ: ENERO" />
                    @error('month_name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Dia') }} *</flux:label>
                    <flux:input wire:model="day_name" placeholder="EJ: LUNES" />
                    @error('day_name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Numero del Dia') }}</flux:label>
                    <flux:input type="number" wire:model="day_number" min="1" max="31" />
                    @error('day_number') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <flux:field>
                    <flux:label>{{ __('Semana') }}</flux:label>
                    <flux:input type="number" wire:model="week" min="1" max="53" />
                    @error('week') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Actividad') }}</flux:label>
                    <flux:input wire:model="activity" placeholder="EJ: DIA DEL ESTUDIANTE" />
                    @error('activity') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
            </div>
            <div class="mt-4">
                <flux:field>
                    <flux:label>{{ __('Feriado') }}</flux:label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="is_holiday" class="rounded border-zinc-300 dark:border-zinc-600 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Marcar como feriado') }}</span>
                    </label>
                    @error('is_holiday') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Actualizar Dia') }}</flux:button>
            <flux:button href="{{ route('admin.settings.calendar-scolars.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
