<?php

declare(strict_types=1);

use App\Models\Setting\YearSettings\ScolarYear;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalle de Año Escolar')] class extends Component {
    public int $yearId;

    public function mount(int $id): void
    {
        $this->yearId = $id;
    }

    public function getYearProperty(): ScolarYear
    {
        return ScolarYear::query()
            ->with(['academicPeriods', 'gradingSchemes', 'calendarDays'])
            ->withCount(['academicPeriods', 'gradingSchemes', 'calendarDays'])
            ->findOrFail($this->yearId);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Detalle de Año Escolar') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Informacion completa del año escolar') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('admin.settings.years.edit', $this->yearId) }}" wire:navigate variant="primary">
                <flux:icon.pencil /> {{ __('Editar') }}
            </flux:button>
            <flux:button href="{{ route('admin.settings.years.index') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.years.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Años Escolares') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $this->year->year_name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                <div class="mx-auto mb-4 flex size-20 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon.calendar class="size-10 text-zinc-400 dark:text-zinc-500" />
                </div>
                <flux:heading size="lg">{{ $this->year->year_name }}</flux:heading>
                <div class="mt-3">
                    <flux:badge color="{{ $this->year->status === 1 ? 'green' : 'red' }}">
                        {{ $this->year->status === 1 ? __('Activo') : __('Inactivo') }}
                    </flux:badge>
                </div>
                <div class="mt-3 flex flex-wrap justify-center gap-2">
                    <flux:badge color="blue">{{ $this->year->academic_periods_count }} {{ __('periodos') }}</flux:badge>
                    <flux:badge color="purple">{{ $this->year->grading_schemes_count }} {{ __('esquemas') }}</flux:badge>
                    <flux:badge color="orange">{{ $this->year->calendar_days_count }} {{ __('dias') }}</flux:badge>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion del Año Escolar') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nombre') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->year->year_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Estado') }}</dt>
                        <dd class="mt-1 text-sm">
                            <flux:badge color="{{ $this->year->status === 1 ? 'green' : 'red' }}">
                                {{ $this->year->status === 1 ? __('Activo') : __('Inactivo') }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Inicio') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->year->start_date?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Fin') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->year->end_date?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Creacion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->year->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Periodos Academicos') }}</flux:heading>
                @forelse ($this->year->academicPeriods as $period)
                    <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $period->trimester_name }}</span>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <flux:icon.calendar-days class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text variant="subtle" class="text-sm">{{ __('Este año no tiene periodos academicos.') }}</flux:text>
                    </div>
                @endforelse
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Esquemas de Calificacion') }}</flux:heading>
                @forelse ($this->year->gradingSchemes as $scheme)
                    <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                            <div >                               
                                {{ __('Formativa') }} {{ number_format($scheme->formative_percentage, 1) }}% ·
                                {{ __('Examen') }} {{ number_format($scheme->exam_percentage, 1) }}% ·
                                {{ __('Proyecto') }} {{ number_format($scheme->project_percentage, 1) }}%
                            </div>                   
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <flux:icon.document-text class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text variant="subtle" class="text-sm">{{ __('Este año no tiene esquemas de calificacion.') }}</flux:text>
                    </div>
                @endforelse
            </div>

          <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="md" class="mb-4">
                    {{ __('Días del Calendario') }}
                </flux:heading>

                @forelse ($this->year->calendarDays as $day)
                    <div
                        class="flex items-center justify-between gap-4 py-3
                        {{ !$loop->last ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-3">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $day->date?->format('d/m/Y') }}
                                </span>

                                <flux:badge
                                    size="sm"
                                    color="{{ $day->is_holiday ? 'red' : 'green' }}"
                                >
                                    {{ $day->is_holiday ? 'Feriado' : 'Día lectivo' }}
                                </flux:badge>
                            </div>

                            @if ($day->activity)
                                <flux:text
                                    variant="subtle"
                                    class="mt-1"
                                >
                                    {{ $day->activity }}
                                </flux:text>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <flux:icon.calendar
                            class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600"
                        />

                        <flux:text variant="subtle" class="text-sm">
                            {{ __('Este año no tiene días del calendario.') }}
                        </flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
