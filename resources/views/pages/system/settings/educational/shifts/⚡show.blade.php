<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\Shift;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalle de Jornada')] class extends Component {
    public int $shiftId;

    public function mount(int $id): void
    {
        $this->shiftId = $id;
    }

    public function getShiftProperty(): Shift
    {
        return Shift::query()
            ->with(['nivels'])
            ->withCount('nivels')
            ->findOrFail($this->shiftId);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Detalle de Jornada') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Informacion completa de la jornada') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('admin.settings.shifts.edit', $this->shiftId) }}" wire:navigate variant="primary">
                <flux:icon.pencil /> {{ __('Editar') }}
            </flux:button>
            <flux:button href="{{ route('admin.settings.shifts.index') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.shifts.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Jornadas') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $this->shift->shift_name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                <div class="mx-auto mb-4 flex size-20 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon.archive-box class="size-10 text-zinc-400 dark:text-zinc-500" />
                </div>
                <flux:heading size="lg">{{ $this->shift->shift_name }}</flux:heading>
                <div class="mt-3">
                    <flux:badge color="{{ $this->shift->status === 1 ? 'green' : 'red' }}">
                        {{ $this->shift->status === 1 ? __('Activa') : __('Inactiva') }}
                    </flux:badge>
                </div>
                <div class="mt-3">
                    <flux:badge color="blue">{{ $this->shift->nivels_count }} {{ __('niveles') }}</flux:badge>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion de la Jornada') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nombre') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->shift->shift_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Creacion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->shift->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Niveles Asociados') }}</flux:heading>
                @forelse ($this->shift->nivels as $nivel)
                    <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $nivel->nivel_name }}</span>
                        <flux:badge size="sm" color="{{ $nivel->status === 1 ? 'green' : 'red' }}">
                            {{ $nivel->status === 1 ? __('Activo') : __('Inactivo') }}
                        </flux:badge>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <flux:icon.adjustments-vertical class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text variant="subtle" class="text-sm">{{ __('Esta jornada no tiene niveles asociados.') }}</flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
