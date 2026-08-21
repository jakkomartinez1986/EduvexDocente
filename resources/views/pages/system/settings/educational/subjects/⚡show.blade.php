<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\Subject;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalle de Asignatura')] class extends Component {
    public int $subjectId;

    public function mount(int $id): void
    {
        $this->subjectId = $id;
    }

    public function getSubjectProperty(): Subject
    {
        return Subject::query()
            ->with(['area'])
            ->findOrFail($this->subjectId);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Detalle de Asignatura') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Informacion completa de la asignatura') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('admin.settings.subjects.edit', $this->subjectId) }}" wire:navigate variant="primary">
                <flux:icon.pencil /> {{ __('Editar') }}
            </flux:button>
            <flux:button href="{{ route('admin.settings.subjects.index') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.subjects.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Asignaturas') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $this->subject->subject_name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                <div class="mx-auto mb-4 flex size-20 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon.bookmark class="size-10 text-zinc-400 dark:text-zinc-500" />
                </div>
                <flux:heading size="lg">{{ $this->subject->subject_name }}</flux:heading>
                <div class="mt-3">
                    <flux:badge color="blue">{{ $this->subject->area?->area_name ?? __('Sin area') }}</flux:badge>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion de la Asignatura') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nombre') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->subject->subject_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Area') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->subject->area?->area_name ?? __('Sin area') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Creacion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->subject->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Ultima Actualizacion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->subject->updated_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion del Area') }}</flux:heading>
                @if ($this->subject->area)
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nombre del Area') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->subject->area->area_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('ID del Area') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->subject->area->id }}</dd>
                        </div>
                    </dl>
                @else
                    <div class="py-8 text-center">
                        <flux:icon.exclamation-circle class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text variant="subtle" class="text-sm">{{ __('Esta asignatura no tiene un area asociada.') }}</flux:text>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
