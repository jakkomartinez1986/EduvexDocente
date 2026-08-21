{{-- Today Summary: Agenda + Quick Actions + Status --}}
@php
    $hoy = ucfirst(now()->isoFormat('dddd'));
    $isTodayWorkingDay = in_array($hoy, array_keys($this->weekDays));
    $agendaHoy = $agenda;
@endphp
@if($isTodayWorkingDay && count($agendaHoy) > 0)
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Agenda del Dia --}}
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-5">
            <h3 class="text-base font-bold text-zinc-800 dark:text-zinc-200 mb-1">{{ __('Agenda de Hoy') }}</h3>
            <p class="text-xs text-zinc-400 dark:text-zinc-500 mb-4">{{ \Carbon\Carbon::now()->translatedFormat('l, d \d\e F Y') }}</p>
            <div class="space-y-0">
                @foreach($agendaHoy as $index => $item)
                    @php
                        $time = $item->start_time instanceof \Carbon\Carbon ? $item->start_time->format('H:i') : $item->start_time;
                    @endphp
                    <div class="flex items-start gap-3 py-2.5 {{ $index < count($agendaHoy) - 1 ? 'border-b border-zinc-100 dark:border-zinc-700' : '' }}">
                        <span class="text-xs font-mono text-zinc-400 dark:text-zinc-500 w-12 flex-shrink-0 pt-0.5">{{ $time }}</span>
                        <div class="flex flex-col items-center gap-1 flex-shrink-0">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            @if($index < count($agendaHoy) - 1)
                                <span class="w-px h-4 bg-zinc-200 dark:bg-zinc-700"></span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $item->subject->subject_name ?? 'N/A' }}</div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                {{ $item->grade->grade_name ?? '' }} {{ $item->grade->section ?? '' }}
                                · {{ $item->start_time->format('H:i') }}–{{ $item->end_time->format('H:i') }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Acciones Rapidas --}}
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-5">
            <h3 class="text-base font-bold text-zinc-800 dark:text-zinc-200 mb-1">{{ __('Acciones Rapidas') }}</h3>
            <p class="text-xs text-zinc-400 dark:text-zinc-500 mb-4">{{ __('Sobre tus clases de hoy') }}</p>
            <div class="space-y-2">
                @foreach($agendaHoy->take(3) as $item)
                    <div class="flex items-center gap-3 p-3 rounded-xl border border-zinc-200 dark:border-zinc-600 hover:border-blue-300 dark:hover:border-blue-600 transition cursor-pointer"
                         wire:click="openAttendanceModal({{ $item->id }})">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                            <flux:icon.clipboard-document-check class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ __('Asistencia') }} · {{ $item->subject->subject_name ?? '' }}</div>
                            <div class="text-[11px] text-zinc-400 dark:text-zinc-500">{{ $item->grade->grade_name ?? '' }} {{ $item->grade->section ?? '' }}</div>
                        </div>
                        <flux:icon.chevron-right class="w-4 h-4 text-zinc-300 dark:text-zinc-600 flex-shrink-0" />
                    </div>
                @endforeach
                @foreach($agendaHoy->take(3) as $item)
                    <div class="flex items-center gap-3 p-3 rounded-xl border border-zinc-200 dark:border-zinc-600 hover:border-amber-300 dark:hover:border-amber-600 transition cursor-pointer"
                         wire:click="openQuickGradesModal({{ $item->id }})">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                            <flux:icon.document-check class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ __('Calificaciones') }} · {{ $item->subject->subject_name ?? '' }}</div>
                            <div class="text-[11px] text-zinc-400 dark:text-zinc-500">{{ $item->grade->grade_name ?? '' }} {{ $item->grade->section ?? '' }}</div>
                        </div>
                        <flux:icon.chevron-right class="w-4 h-4 text-zinc-300 dark:text-zinc-600 flex-shrink-0" />
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Resumen de Estado --}}
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-5">
            <h3 class="text-base font-bold text-zinc-800 dark:text-zinc-200 mb-1">{{ __('Resumen de Estado') }}</h3>
            <p class="text-xs text-zinc-400 dark:text-zinc-500 mb-4">{{ __('Estado de tus horarios de hoy') }}</p>
            <div class="space-y-3">
                @php
                    $hoyUpper = mb_strtoupper($hoy, 'UTF-8');
                    $horariosHoy = $horariosPorDia[$hoy] ?? collect();
                    $totalHoy = $horariosHoy->count();
                    $activosHoy = $horariosHoy->where('is_active', true)->count();
                    $inactivosHoy = $totalHoy - $activosHoy;
                @endphp
                <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Total clases hoy') }}</span>
                    <span class="text-lg font-bold text-zinc-900 dark:text-zinc-100 font-mono">{{ $totalHoy }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                    <span class="text-sm text-emerald-700 dark:text-emerald-400">{{ __('Horarios activos') }}</span>
                    <span class="text-lg font-bold text-emerald-700 dark:text-emerald-400 font-mono">{{ $activosHoy }}</span>
                </div>
                @if($inactivosHoy > 0)
                    <div class="flex items-center justify-between p-3 rounded-lg bg-red-50 dark:bg-red-900/20">
                        <span class="text-sm text-red-700 dark:text-red-400">{{ __('Horarios inactivos') }}</span>
                        <span class="text-lg font-bold text-red-700 dark:text-red-400 font-mono">{{ $inactivosHoy }}</span>
                    </div>
                @endif
                @php
                    $gradeIdsHoy = $horariosHoy->pluck('grade_id')->unique()->toArray();
                    $totalAlumnosHoy = $this->getStudentsCountForGrades($gradeIdsHoy);
                @endphp
                <div class="flex items-center justify-between p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                    <span class="text-sm text-blue-700 dark:text-blue-400">{{ __('Estudiantes afectados') }}</span>
                    <span class="text-lg font-bold text-blue-700 dark:text-blue-400 font-mono">{{ $totalAlumnosHoy }}</span>
                </div>
            </div>
        </div>
    </div>
@endif
