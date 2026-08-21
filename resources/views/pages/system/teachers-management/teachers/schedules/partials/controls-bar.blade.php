{{-- Controls Bar: Week Navigation + Day Selector + Schedule Type + Jornada --}}
<div class="mb-6 flex flex-wrap items-center gap-3">
    {{-- Week Navigation --}}
    <button wire:click="previousWeek" class="w-9 h-9 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 flex items-center justify-center text-zinc-500 hover:bg-zinc-50 transition">
        <flux:icon.chevron-left />
    </button>
    <div class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm font-semibold">
        <flux:icon.calendar />
        <span>{{ \Carbon\Carbon::parse($this->weekStart)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($this->weekEnd)->format('d/m/Y') }}</span>
    </div>
    <button wire:click="nextWeek" class="w-9 h-9 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 flex items-center justify-center text-zinc-500 hover:bg-zinc-50 transition">
        <flux:icon.chevron-right />
    </button>

    {{-- Day Selector --}}
    <div class="flex items-center gap-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl px-2 py-1.5">
        @foreach($this->weekDays as $dia => $fecha)
            <button wire:click="selectDay('{{ $dia }}')"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200
                {{ $this->selectedDay === $dia
                    ? 'bg-blue-600 text-white shadow-sm'
                    : 'text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}">
                {{ strtoupper(substr($dia, 0, 3)) }}
            </button>
        @endforeach
    </div>

    {{-- Schedule Type Filter --}}
    <div class="flex gap-1 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-1">
        @foreach($this->scheduleTypes as $key => $type)
            <button wire:click="selectScheduleType('{{ $key }}')"
                    class="px-3 py-1.5 rounded-md text-xs font-semibold transition
                           {{ $this->activeScheduleType === $key
                               ? 'bg-blue-600 text-white shadow-sm'
                               : 'text-zinc-600 dark:text-zinc-400 hover:bg-white dark:hover:bg-zinc-700' }}">
                {{ $type['name'] }}
            </button>
        @endforeach
    </div>

    {{-- Jornada Filter --}}
    <div class="ml-auto flex items-center gap-2 text-sm text-zinc-500">
        <flux:icon.clock />
        <span class="font-semibold">{{ __('Jornada:') }}</span>
        <div class="flex gap-1">
            <button wire:click="selectJornada('TODAS')"
                    class="px-2.5 py-1 rounded-md text-xs font-semibold transition
                           {{ $this->selectedJornada === 'TODAS'
                               ? 'bg-blue-600 text-white'
                               : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}">
                {{ __('Todas') }}
            </button>
            @foreach($this->jornadas as $j)
                <button wire:click="selectJornada('{{ $j['name'] }}')"
                        class="px-2.5 py-1 rounded-md text-xs font-semibold transition
                               {{ $this->selectedJornada === $j['name']
                                   ? 'bg-blue-600 text-white'
                                   : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}">
                    {{ $j['name'] }}
                </button>
            @endforeach
        </div>
    </div>
</div>
