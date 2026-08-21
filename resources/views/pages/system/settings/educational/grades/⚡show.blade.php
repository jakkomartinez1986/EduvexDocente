<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\Grade;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalle de Grado')] class extends Component {
    public int $gradeId;

    public function mount(int $id): void
    {
        $this->gradeId = $id;
    }

    public function getGradeProperty(): Grade
    {
        return Grade::query()
            ->with(['nivel.shift', 'parallels'])
            ->withCount('parallels')
            ->findOrFail($this->gradeId);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Detalle de Grado') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Informacion completa del grado') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('admin.settings.grades.edit', $this->gradeId) }}" wire:navigate variant="primary">
                <flux:icon.pencil /> {{ __('Editar') }}
            </flux:button>
            <flux:button href="{{ route('admin.settings.grades.index') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.grades.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Grados') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $this->grade->grade_name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                <div class="mx-auto mb-4 flex size-20 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon.academic-cap class="size-10 text-zinc-400 dark:text-zinc-500" />
                </div>
                <flux:heading size="lg">{{ $this->grade->grade_name }}</flux:heading>
                <div class="mt-3">
                    <flux:badge color="{{ $this->grade->status === 1 ? 'green' : 'red' }}">
                        {{ $this->grade->status === 1 ? __('Activo') : __('Inactivo') }}
                    </flux:badge>
                </div>
                <div class="mt-3">
                    <flux:badge color="blue">{{ $this->grade->parallels_count }} {{ __('paralelos') }}</flux:badge>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion del Grado') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nombre') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->grade->grade_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Seccion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->grade->section ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nivel') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->grade->nivel->nivel_name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Turno') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->grade->nivel->shift->shift_name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Creacion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->grade->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Paralelos Asociados') }}</flux:heading>
                @forelse ($this->grade->parallels as $parallel)
                    <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                        <div class="flex items-center gap-3">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $parallel->parallel_name }}</span>
                            <flux:badge size="sm" color="gray">{{ $parallel->code }}</flux:badge>
                        </div>
                        <flux:badge size="sm" color="{{ $parallel->status === 1 ? 'green' : 'red' }}">
                            {{ $parallel->status === 1 ? __('Activo') : __('Inactivo') }}
                        </flux:badge>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <flux:icon.users class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text variant="subtle" class="text-sm">{{ __('Este grado no tiene paralelos asociados.') }}</flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
