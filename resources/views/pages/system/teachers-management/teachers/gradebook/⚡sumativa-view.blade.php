@php
    $studentPaginator = $this->getStudents();
@endphp

<div wire:key="view-sumativa" class="mb-4">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 mb-1">{{ __('Calificaciones Formativas') }}</h3>
            <p class="text-xs text-zinc-500">{{ __('Promedio formativo, examen, proyecto y total ponderado.') }}</p>
        </div>
        @if($this->isSumativaAvailable($selectedTrimesterId))
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                {{ __('Período abierto') }}
            </span>
        @else
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                {{ __('Período cerrado') }}
            </span>
        @endif
    </div>
</div>

<div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 sticky left-0 bg-zinc-50 dark:bg-zinc-800/50 z-10 border-r border-zinc-200 dark:border-zinc-700">
                    {{ __('Estudiante') }}
                </th>
                <th class="px-3 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400 min-w-[80px]">
                    {{ __('Prom. Form') }}
                </th>
                <th class="px-3 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400 min-w-[80px]">
                    {{ __('Examen') }}
                </th>
                <th class="px-3 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400 min-w-[80px]">
                    {{ __('Proyecto') }}
                </th>
                <th class="px-3 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400 min-w-[80px] bg-blue-50 dark:bg-blue-900/20 border-l-2 border-blue-300 dark:border-blue-700">
                    {{ __('Total') }}
                </th>
                <th class="px-3 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400 min-w-[100px]">
                    {{ __('Estado') }}
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($studentPaginator as $student)
                @php
                    $formativeAvg = $this->getStudentFormativeAverage($student->id);
                    $totalAvg = $this->getStudentTotalAverage($student->id);
                    $examGrade = $exams[$student->id]['grade'] ?? null;
                    $projectGrade = $projects[$student->id]['grade'] ?? null;
                    $status = match(true) {
                        $totalAvg === null => 'Sin datos',
                        $totalAvg >= 7 => 'Aprobado',
                        $totalAvg >= 5 => 'Supletorio',
                        default => 'Reprobado',
                    };
                @endphp
                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                    <td class="px-4 py-2.5 font-medium text-zinc-900 dark:text-zinc-100 text-xs sticky left-0 bg-white dark:bg-zinc-900 z-10 border-r border-zinc-200 dark:border-zinc-700">
                        {{ $student->user?->full_name ?? $student->user?->lastname . ' ' . $student->user?->name }}
                        <span class="block text-[10px] font-mono text-zinc-400">{{ $student->student_code }}</span>
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        <span class="font-mono text-xs {{ $this->getPerformanceColor($formativeAvg) }}">
                            {{ $formativeAvg !== null ? number_format($formativeAvg, 2) : '—' }}
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        @if($this->isSumativaAvailable($selectedTrimesterId))
                            <input type="number" min="0" max="10" step="0.1"
                                   class="w-16 px-1.5 py-1 border border-zinc-200 dark:border-zinc-600 rounded text-xs font-mono text-center bg-white dark:bg-zinc-800 focus:outline-2 focus:outline-blue-500"
                                   value="{{ $examGrade ?? '' }}"
                                   placeholder="—"
                                   wire:change="saveExamGrade({{ $student->id }}, $event.target.value)">
                        @else
                            <span class="font-mono text-xs {{ $this->getPerformanceColor($examGrade) }}">
                                {{ $examGrade !== null ? number_format($examGrade, 2) : '—' }}
                            </span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        @if($this->isSumativaAvailable($selectedTrimesterId))
                            <input type="number" min="0" max="10" step="0.1"
                                   class="w-16 px-1.5 py-1 border border-zinc-200 dark:border-zinc-600 rounded text-xs font-mono text-center bg-white dark:bg-zinc-800 focus:outline-2 focus:outline-blue-500"
                                   value="{{ $projectGrade ?? '' }}"
                                   placeholder="—"
                                   wire:change="saveProjectGrade({{ $student->id }}, $event.target.value)">
                        @else
                            <span class="font-mono text-xs {{ $this->getPerformanceColor($projectGrade) }}">
                                {{ $projectGrade !== null ? number_format($projectGrade, 2) : '—' }}
                            </span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-center font-bold border-l-2 border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20">
                        <span class="font-mono text-xs {{ $this->getPerformanceColor($totalAvg) }}">
                            {{ $totalAvg !== null ? number_format($totalAvg, 2) : '—' }}
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold
                            {{ match($status) {
                                'Aprobado' => 'bg-emerald-50 text-emerald-700',
                                'Supletorio' => 'bg-amber-50 text-amber-700',
                                'Reprobado' => 'bg-red-50 text-red-700',
                                default => 'bg-zinc-100 text-zinc-500',
                            } }}">
                            {{ $status }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center">
                        <flux:icon.archive-box class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                        <flux:text variant="subtle" class="text-sm">{{ __('No hay estudiantes matriculados.') }}</flux:text>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $studentPaginator->links() }}</div>
