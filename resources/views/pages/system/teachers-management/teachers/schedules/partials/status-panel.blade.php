{{-- Status Panel: Active/Inactive counts + bulk actions --}}
@php
    $totalActive = $stats['active_count'];
    $totalInactive = $stats['inactive_count'];
@endphp
<div class="mb-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            @php $stColor = $this->scheduleTypes[$this->activeScheduleType]['color']; @endphp
            <div class="w-10 h-10 rounded-xl bg-{{ $stColor }}-100 dark:bg-{{ $stColor }}-900/30 flex items-center justify-center">
                @if($this->activeScheduleType === 'OFFICIAL')
                    <flux:icon.book-open class="w-5 h-5 text-{{ $stColor }}-600" />
                @elseif($this->activeScheduleType === 'EVALUATION')
                    <flux:icon.document-text class="w-5 h-5 text-{{ $stColor }}-600" />
                @elseif($this->activeScheduleType === 'TEST')
                    <flux:icon.beaker class="w-5 h-5 text-{{ $stColor }}-600" />
                @elseif($this->activeScheduleType === 'MAKEUP')
                    <flux:icon.arrow-path class="w-5 h-5 text-{{ $stColor }}-600" />
                @endif
            </div>
            <div>
                <h3 class="font-bold text-zinc-800 dark:text-zinc-200 text-lg">
                    {{ $this->scheduleTypes[$this->activeScheduleType]['name'] }}
                </h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $stats['total_slots'] }} horas semanales · {{ $this->selectedJornada === 'TODAS' ? __('Todas las jornadas') : $this->selectedJornada }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 items-center">
            @if($totalInactive > 0)
                <button wire:click="activateAll"
                    class="flex items-center gap-2 py-2 px-4 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg text-sm font-medium transition">
                    <flux:icon.check /> {{ __('Activar Todos') }}
                </button>
            @endif
            @if($totalActive > 0)
                <button wire:click="deactivateAll"
                    class="flex items-center gap-2 py-2 px-4 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition">
                    <flux:icon.x-mark /> {{ __('Desactivar Todos') }}
                </button>
            @endif
            <div class="flex items-center gap-4 text-sm">
                <span class="text-emerald-600 dark:text-emerald-400 font-medium">{{ $totalActive }} {{ __('activos') }}</span>
                <span class="text-red-600 dark:text-red-400 font-medium">{{ $totalInactive }} {{ __('inactivos') }}</span>
            </div>
        </div>
    </div>
</div>
