<?php

declare(strict_types=1);

use App\Models\Setting\YearSettings\AcademicPeriod;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalle de Periodo Academico')] class extends Component {
    public int $periodId;

    public function mount(int $id): void
    {
        $this->periodId = $id;
    }

    public function getPeriodProperty(): AcademicPeriod
    {
        return AcademicPeriod::query()
            ->with('year')
            ->findOrFail($this->periodId);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Detalle de Periodo Academico') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Informacion completa del periodo') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('admin.settings.trimesters.edit', $this->periodId) }}" wire:navigate variant="primary">
                <flux:icon.pencil /> {{ __('Editar') }}
            </flux:button>
            <flux:button href="{{ route('admin.settings.trimesters.index') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.trimesters.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Periodos Academicos') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $this->period->trimester_name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                <div class="mx-auto mb-4 flex size-20 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon.calendar-days class="size-10 text-zinc-400 dark:text-zinc-500" />
                </div>
                <flux:heading size="lg">{{ $this->period->trimester_name }}</flux:heading>
                <div class="mt-3">
                    <flux:badge color="{{ $this->period->status === 1 ? 'green' : 'red' }}">
                        {{ $this->period->status === 1 ? __('Activo') : __('Inactivo') }}
                    </flux:badge>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion del Periodo') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nombre del Trimestre') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->period->trimester_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Ano Escolar') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->period->year->year_name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Inicio') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->period->start_date?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Fin') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->period->end_date?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Apertura de Calificaciones') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->period->grading_open_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Cierre de Calificaciones') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->period->grading_close_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Tipo de Periodo') }}</dt>
                        <dd class="mt-1">
                            @if($this->period->is_supletorio)
                                <flux:badge color="red" size="sm">{{ __('Supletorio') }}</flux:badge>
                            @else
                                <flux:badge color="blue" size="sm">{{ __('Regular') }}</flux:badge>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Estado') }}</dt>
                        <dd class="mt-1">
                            <flux:badge color="{{ $this->period->status === 1 ? 'green' : 'red' }}" size="sm">
                                {{ $this->period->status === 1 ? __('Activo') : __('Inactivo') }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Creacion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->period->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
