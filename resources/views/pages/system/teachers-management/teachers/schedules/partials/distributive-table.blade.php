{{-- Distributive Table --}}
<div class="lg:col-span-3">
    <h3 class="text-lg font-bold text-zinc-800 dark:text-zinc-200 mb-4">{{ __('Distributivo Docente') }}</h3>

    @if(in_array($this->activeScheduleType, ['OFFICIAL', 'TEST']))
        @php
            $gruposDistributivo = collect($distributivo);

            $totalMinutosBT = 0;
            $totalMinutosEGB = 0;
            foreach ($gruposDistributivo as $row) {
                foreach ($row['schedules'] as $sch) {
                    $mins = $sch->start_time->diffInMinutes($sch->end_time);
                    $nivel = strtolower(optional($sch->grade->nivel)->nivel_name ?? '');
                    $norm = mb_strtolower(str_replace('_', ' ', \Transliterator::create('Any-Latin; Latin-ASCII; Lower')->transliterate(optional($sch->grade->nivel)->nivel_name ?? '')));
                    if (str_contains($norm, 'bachillerato tecnico')) {
                        $totalMinutosBT += $mins;
                    } else {
                        $totalMinutosEGB += $mins;
                    }
                }
            }
            $totalBloquesBT = $totalMinutosBT > 0 ? round($totalMinutosBT / 40, 1) : 0;
            $totalBloquesEGB = $totalMinutosEGB > 0 ? round($totalMinutosEGB / 45, 1) : 0;
            $totalHorasBT = $totalMinutosBT > 0 ? round($totalMinutosBT / 60, 1) : 0;
            $totalHorasEGB = $totalMinutosEGB > 0 ? round($totalMinutosEGB / 60, 1) : 0;
            $totalHoras = $totalBloquesBT + $totalBloquesEGB;
        @endphp

        {{-- Summary Badges --}}
        <div class="flex flex-wrap gap-2 mb-4">
            @if($totalMinutosBT > 0)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 font-medium text-sm border border-amber-200 dark:border-amber-800">
                    <flux:icon.clock class="size-3.5" />
                    BT: {{ $totalBloquesBT }} h ({{ $totalBloquesBT }} bloques de 40 min)
                </span>
            @endif
            @if($totalMinutosEGB > 0)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 font-medium text-sm border border-blue-200 dark:border-blue-800">
                    <flux:icon.clock class="size-3.5" />
                    EGB/BGU: {{ $totalBloquesEGB }} h ({{ $totalBloquesEGB }} bloques de 45 min)
                </span>
            @endif
            <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 font-bold text-sm border border-emerald-200 dark:border-emerald-800">
                {{ __('Total:') }} {{ $totalHoras }} h
            </span>
        </div>

        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Asignatura') }}</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Curso') }}</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Jornada') }}</th>
                            <th class="px-5 py-3 text-center text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Estudiantes') }}</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Horas/Sem') }}</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-700">
                        @forelse($gruposDistributivo as $row)
                            @php
                                $firstSchedule = $row['schedules']->first();
                                $minutosRow = 0;
                                foreach ($row['schedules'] as $sch) {
                                    $minutosRow += $sch->start_time->diffInMinutes($sch->end_time);
                                }
                                $nivelRow = strtolower(optional($firstSchedule->grade->nivel)->nivel_name ?? '');
                                $normRow = mb_strtolower(str_replace('_', ' ', \Transliterator::create('Any-Latin; Latin-ASCII; Lower')->transliterate(optional($firstSchedule->grade->nivel)->nivel_name ?? '')));
                                $bloqueRow = str_contains($normRow, 'bachillerato tecnico') ? 40 : 45;
                                $bloquesRow = intdiv($minutosRow, $bloqueRow);
                            @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded bg-blue-500 flex-shrink-0"></span>
                                        <div>
                                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $row['subject_name'] }}</div>
                                            <div class="text-[11px] text-zinc-400 dark:text-zinc-500">{{ optional($firstSchedule->subject->area)->area_name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ $row['grade_name'] }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                                        {{ ($row['shift_name'] ?? '') === 'Matutina' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' : (($row['shift_name'] ?? '') === 'Vespertina' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400' : 'bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300') }}">
                                        {{ $row['shift_name'] ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                        <flux:icon.user-group class="size-3" />
                                        {{ $row['student_count'] }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $bloquesRow }} bloques</div>
                                    <div class="text-[11px] text-zinc-400 dark:text-zinc-500">{{ $minutosRow }} min ({{ $bloqueRow }} min c/u)</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-1.5">
                                        <a href="{{ route('system.identity.students.import', $firstSchedule->grade_id) }}" wire:navigate
                                           class="w-7 h-7 rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 flex items-center justify-center text-zinc-400 hover:border-emerald-500 hover:text-emerald-600 transition"
                                           title="{{ __('Importar estudiantes') }}">
                                            <flux:icon.user-plus class="size-3.5" />
                                        </a>
                                        <button wire:click="openAttendanceModal({{ $firstSchedule->id }})"
                                           class="w-7 h-7 rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 flex items-center justify-center text-zinc-400 hover:border-blue-500 hover:text-blue-600 transition"
                                           title="{{ __('Tomar asistencia') }}">
                                            <flux:icon.clipboard-document-check class="size-3.5" />
                                        </button>
                                        <button wire:click="openQuickGradesModal({{ $firstSchedule->id }})"
                                           class="w-7 h-7 rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 flex items-center justify-center text-zinc-400 hover:border-amber-500 hover:text-amber-600 transition"
                                           title="{{ __('Registrar calificaciones') }}">
                                            <flux:icon.document-check class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center">
                                    <flux:icon.archive-box class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                                    <flux:text variant="subtle" class="text-sm">{{ __('No hay horas registradas.') }}</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-4 bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-200 dark:border-zinc-700">
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                    <span>{{ __('Total:') }} <strong class="text-zinc-700 dark:text-zinc-300">{{ $totalHoras }}</strong> {{ __('horas clase') }}</span>
                    <span>{{ __('Distribuidos en') }} <strong class="text-zinc-700 dark:text-zinc-300">{{ count($distributivo) }}</strong> {{ __('grupos') }}</span>
                </div>
            </div>
        </div>
    @else
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-8 text-center">
            <flux:text variant="subtle" class="text-sm">{{ __('El distributivo solo esta disponible para Horarios Oficiales y de Prueba.') }}</flux:text>
        </div>
    @endif
</div>
