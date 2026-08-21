<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\Shift;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Jornadas')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 15;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getRecordsProperty()
    {
        return Shift::query()
            ->withCount('nivels')
            ->when($this->search, fn ($q) =>
                $q->where('shift_name', 'ilike', "%{$this->search}%")
            )
            ->latest()
            ->paginate($this->perPage);
    }

    public function confirmToggle(int $id): void
    {
        $this->dispatch('showConfirm',
            message: __('Esta seguro de cambiar el estado de esta jornada?'),
            eventName: 'execute-toggle-shift',
            eventParams: ['id' => $id]
        )->to('confirm-action');
    }

    #[On('execute-toggle-shift')]
    public function executeToggleShift(array $params): void
    {
        $shift = Shift::find($params['id']);
        if ($shift) {
            $this->toggleStatus($shift);
        }
    }

    public function toggleStatus(Shift $shift): void
    {
        $shift->status = $shift->status === 1 ? 0 : 1;
        $shift->save();

        Flux::toast(
            variant: 'success',
            text: "Jornada {$shift->shift_name} " . ($shift->status === 1 ? 'activada' : 'desactivada') . ' correctamente.'
        );
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Jornadas') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Gestion de jornadas academicas') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.settings.shifts.create') }}" wire:navigate variant="primary">
            <flux:icon.plus /> {{ __('Nueva Jornada') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Jornadas') }}</span>
    </nav>

    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-6">
        <div class="w-full sm:w-96">
            <flux:input wire:model.live.debounce="search" :placeholder="__('Buscar por nombre de jornada...')" icon="magnifying-glass" />
        </div>
    </div>

    <div>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Nombre') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Niveles') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estado') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->records as $shift)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $shift->shift_name }}</td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" color="blue">{{ $shift->nivels_count }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="confirmToggle({{ $shift->id }})"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium cursor-pointer transition
                                            {{ $shift->status === 1
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 hover:bg-emerald-100'
                                                : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-100' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $shift->status === 1 ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                    {{ $shift->status === 1 ? __('Activa') : __('Inactiva') }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item href="{{ route('admin.settings.shifts.show', $shift->id) }}" wire:navigate icon="eye">{{ __('Ver') }}</flux:menu.item>
                                        <flux:menu.item href="{{ route('admin.settings.shifts.edit', $shift->id) }}" wire:navigate icon="pencil">{{ __('Editar') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-16 text-center">
                                <flux:icon.archive-box class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron jornadas.') }}</flux:text>
                                <flux:text variant="subtle" class="text-xs mt-1">{{ __('Intente con otros terminos de busqueda.') }}</flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $this->records->links() }}</div>
    </div>

    <livewire:confirm-action />
</div>
