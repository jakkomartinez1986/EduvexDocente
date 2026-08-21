@php
    $studentPaginator = $this->getStudents();
@endphp

<div wire:key="view-supletorio" class="mb-4">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 mb-1">{{ __('Calificaciones Supletorio') }}</h3>
            <p class="text-xs text-zinc-500">{{ __('Ingrese la calificacion final de supletorio para cada estudiante.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="filled" color="red" icon="printer"
                         href="{{ route('admin.summaries.gradebook.pdf.supletorio-report', [
                             'subject_id' => $selectedSubjectId,
                             'grade_id' => $selectedGradeId,
                         ]) }}"
                         title="{{ __('Imprimir reporte de supletorio') }}">
                {{ __('Imprimir Reporte') }}
            </flux:button>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                {{ __('Solo despues de los 3 trimestres') }}
            </span>
        </div>
    </div>
</div>

<div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estudiante') }}</th>
                @foreach($trimesters as $t)
                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ $t['trimester_name'] }}</th>
                @endforeach
                <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Prom. Anual') }}</th>
                <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Supletorio') }}</th>
                <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estado') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($studentPaginator as $student)
                @php
                    $annualAvg = $this->getAnnualAverage($student->id);
                    $supletorio = $supletorios[$student->id] ?? null;
                    $supletorioGrade = $supletorio['grade'] ?? null;
                    $aprobadoSupletorio = $supletorioGrade !== null && $supletorioGrade >= 7;
                    $reprobado = $annualAvg !== null && $annualAvg < 5;
                    $approved = $annualAvg !== null && $annualAvg >= 7;
                    $status = $aprobadoSupletorio
                        ? 'Aprobado por Supletorio'
                        : ($annualAvg !== null
                            ? ($approved ? 'Aprobado' : ($reprobado ? 'Reprobado' : 'Supletorio'))
                            : 'Sin datos');
                @endphp
                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                    <td class="px-4 py-2.5 font-medium text-zinc-900 dark:text-zinc-100 text-xs">
                        {{ $student->user?->full_name ?? $student->user?->lastname . ' ' . $student->user?->name }}
                        <span class="block text-[10px] font-mono text-zinc-400">{{ $student->student_code }}</span>
                    </td>
                    @foreach($trimesters as $t)
                        @php $tTotal = $this->getTrimesterTotal($student->id, $t['id']); @endphp
                        <td class="px-4 py-2.5 text-center">
                            <span class="font-mono text-xs {{ $this->getPerformanceColor($tTotal) }}">
                                {{ $tTotal !== null ? number_format($tTotal, 2) : '—' }}
                            </span>
                        </td>
                    @endforeach
                    <td class="px-4 py-2.5 text-center">
                        <span class="font-mono text-xs font-bold {{ $this->getPerformanceColor($annualAvg) }}">
                            {{ $annualAvg !== null ? number_format($annualAvg, 2) : '—' }}
                        </span>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        <input type="number" min="0" max="10" step="0.1"
                               class="w-16 px-1.5 py-1 border border-zinc-200 dark:border-zinc-600 rounded text-xs font-mono text-center bg-white dark:bg-zinc-800 focus:outline-2 focus:outline-blue-500"
                               value="{{ $supletorioGrade ?? '' }}"
                               placeholder="—"
                               wire:change="saveSupletorioGrade({{ $student->id }}, $event.target.value)">
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold
                            {{ match(true) {
                                $aprobadoSupletorio => 'bg-emerald-50 text-emerald-700',
                                $approved => 'bg-emerald-50 text-emerald-700',
                                $reprobado => 'bg-red-50 text-red-700',
                                $status === 'Supletorio' => 'bg-amber-50 text-amber-700',
                                default => 'bg-zinc-100 text-zinc-500',
                            } }}">
                            {{ $status }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($trimesters) + 4 }}" class="px-4 py-12 text-center">
                        <flux:icon.archive-box class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                        <flux:text variant="subtle" class="text-sm">{{ __('No hay estudiantes matriculados.') }}</flux:text>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $studentPaginator->links() }}</div>
