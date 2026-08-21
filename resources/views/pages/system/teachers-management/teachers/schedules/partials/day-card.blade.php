{{-- Day Card: Expandable day with schedule cards --}}
@php
    $coloresTipo = [
        'OFFICIAL'   => ['bg' => 'bg-blue-50 dark:bg-blue-900/20',  'border' => 'border-blue-400 dark:border-blue-500', 'text' => 'text-blue-700 dark:text-blue-400'],
        'EVALUATION' => ['bg' => 'bg-red-50 dark:bg-red-900/20',    'border' => 'border-red-400 dark:border-red-500',   'text' => 'text-red-700 dark:text-red-400'],
        'TEST'       => ['bg' => 'bg-amber-50 dark:bg-amber-900/20','border' => 'border-amber-400 dark:border-amber-500','text' => 'text-amber-700 dark:text-amber-400'],
        'MAKEUP'     => ['bg' => 'bg-purple-50 dark:bg-purple-900/20','border' => 'border-purple-400 dark:border-purple-500','text' => 'text-purple-700 dark:text-purple-400'],
    ];
@endphp

<div class="mb-8 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
    @foreach($this->weekDays as $dia => $fecha)
        @php
            $isToday = $dia === ucfirst(now()->isoFormat('dddd'));
            $isExpanded = in_array($dia, $this->expandedDays);
            $horariosDelDia = $horariosPorDia[$dia] ?? collect();
        @endphp

        @if($isExpanded)
            <div class="border-b border-zinc-100 dark:border-zinc-700 last:border-b-0">
                <div class="flex items-start gap-4 p-5">
                    {{-- Day Label --}}
                    <div class="w-24 flex-shrink-0 pt-1">
                        <span class="text-sm font-bold {{ $isToday ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-800 dark:text-zinc-200' }}">
                            {{ strtoupper($dia) }}
                        </span>
                        <span class="block text-[10px] text-zinc-400 mt-0.5">{{ \Carbon\Carbon::parse($fecha)->format('d/m') }}</span>
                        @if($isToday)
                            <span class="mt-1 inline-block text-[10px] font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded-full">
                                HOY
                            </span>
                        @endif
                    </div>

                    {{-- Schedule Cards --}}
                    <div class="flex-1 min-w-0">
                        @if($horariosDelDia->count() > 0)
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($horariosDelDia as $horario)
                                    @php
                                        $colores = $coloresTipo[$horario->schedule_type] ?? $coloresTipo['OFFICIAL'];
                                    @endphp
                                    @include('pages::system.teachers-management.teachers.schedules.partials.schedule-card', [
                                        'horario' => $horario,
                                        'colores' => $colores,
                                        'yearId' => $this->yearId,
                                    ])
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6">
                                <flux:icon.archive-box class="w-10 h-10 text-zinc-300 dark:text-zinc-600 mx-auto mb-2" />
                                <p class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('No hay clases para este dia') }}{{ $this->selectedJornada !== 'TODAS' ? ' en ' . $this->selectedJornada : '' }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Collapse Button --}}
                <button wire:click="toggleDay('{{ $dia }}')"
                    class="w-full flex items-center justify-center gap-2 px-5 py-3 border-t border-zinc-100 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                    <span class="text-xs font-semibold text-zinc-400 dark:text-zinc-500">{{ __('Ocultar horario') }}</span>
                    <flux:icon.chevron-up class="w-4 h-4 text-zinc-400 dark:text-zinc-500" />
                </button>
            </div>
        @else
            <div class="border-b border-zinc-100 dark:border-zinc-700 last:border-b-0">
                <button wire:click="toggleDay('{{ $dia }}')"
                    class="w-full flex items-center justify-between px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-bold {{ $isToday ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-800 dark:text-zinc-200' }}">
                            {{ strtoupper($dia) }}
                        </span>
                        @if($isToday)
                            <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded-full">HOY</span>
                        @endif
                        <span class="text-xs text-zinc-400 dark:text-zinc-500">
                            {{ $horariosDelDia->count() }} {{ __('clase(s)') }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-blue-600 dark:text-blue-400">
                        {{ __('Ver horario completo') }}
                        <flux:icon.chevron-down class="w-4 h-4" />
                    </div>
                </button>
            </div>
        @endif
    @endforeach
</div>
