<?php

declare(strict_types=1);

use App\Models\Setting\YearSettings\GradingScheme;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalle de Esquema de Calificacion')] class extends Component {
    public int $schemeId;

    public function mount(int $id): void
    {
        $this->schemeId = $id;
    }

    public function getGradingSchemeProperty(): GradingScheme
    {
        return GradingScheme::query()
            ->with('year')
            ->findOrFail($this->schemeId);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Detalle de Esquema de Calificacion') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Informacion completa del esquema de calificacion') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('admin.settings.grading-schemes.edit', $this->schemeId) }}" wire:navigate variant="primary">
                <flux:icon.pencil /> {{ __('Editar') }}
            </flux:button>
            <flux:button href="{{ route('admin.settings.grading-schemes.index') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.grading-schemes.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Esquemas de Calificacion') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $this->gradingScheme->year->year_name ?? '-' }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                <div class="mx-auto mb-4 flex size-20 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon.document-chart-bar class="size-10 text-zinc-400 dark:text-zinc-500" />
                </div>
                <flux:heading size="lg">{{ $this->gradingScheme->year->year_name ?? '-' }}</flux:heading>
                <div class="mt-3">
                    <flux:badge color="{{ $this->gradingScheme->status === 1 ? 'green' : 'red' }}">
                        {{ $this->gradingScheme->status === 1 ? __('Activo') : __('Inactivo') }}
                    </flux:badge>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Porcentajes de Calificacion') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Porcentaje Formativo') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->gradingScheme->formative_percentage }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Porcentaje Sumativo') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->gradingScheme->summative_percentage }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Porcentaje de Examen') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->gradingScheme->exam_percentage }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Porcentaje de Proyecto') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->gradingScheme->project_percentage }}%</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion del Ano Escolar') }}</flux:heading>
                @if ($this->gradingScheme->year)
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nombre del Ano') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->gradingScheme->year->year_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Estado del Ano') }}</dt>
                            <dd class="mt-1 text-sm">
                                <flux:badge size="sm" color="{{ $this->gradingScheme->year->status === 1 ? 'green' : 'red' }}">
                                    {{ $this->gradingScheme->year->status === 1 ? __('Activo') : __('Inactivo') }}
                                </flux:badge>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Inicio') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->gradingScheme->year->start_date?->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Fin') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->gradingScheme->year->end_date?->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Creacion') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->gradingScheme->created_at?->format('d/m/Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Ultima Actualizacion') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->gradingScheme->updated_at?->format('d/m/Y H:i') }}</dd>
                        </div>
                    </dl>
                @else
                    <div class="py-8 text-center">
                        <flux:icon.exclamation-circle class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text variant="subtle" class="text-sm">{{ __('Este esquema no tiene un ano escolar asociado.') }}</flux:text>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
