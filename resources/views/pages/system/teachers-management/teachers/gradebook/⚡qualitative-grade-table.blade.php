@php
    $studentPaginator = $this->getStudents();
    $isReading = $this->isReadingPromotion();
    $hasEjeGrouping = in_array($this->qualitativeType, ['career_guidance', 'classroom_support']);
@endphp

<div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <div class="p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 mb-1">
                    {{ __('Calificacion Cualitativa') }} — {{ $this->getSubjectName() }}
                </h3>
                <p class="text-xs text-zinc-500">{{ __('Seleccione el valor para cada indicador por estudiante.') }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if($this->isGradingOpen())
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        {{ __('Periodo abierto') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                        {{ __('Periodo cerrado') }}
                    </span>
                @endif
                <flux:button size="sm" variant="filled" color="blue" icon="printer"
                             href="{{ route('admin.summaries.gradebook.pdf.qualitative-report', [
                                 'subject_id' => $selectedSubjectId,
                                 'grade_id' => $selectedGradeId,
                                 'trimester_id' => $selectedTrimesterId,
                             ]) }}"
                             title="{{ __('Imprimir reporte cualitativo') }}">
                    {{ __('Imprimir Reporte') }}
                </flux:button>
            </div>
        </div>

        @if($selectedTrimesterId != -1 && count($qualitativeIndicators) > 0)
            @php
                $groupedByEje = $hasEjeGrouping ? collect($qualitativeIndicators)->groupBy(fn ($i) => $i['eje'] ?? 'General') : null;
            @endphp
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 sticky left-0 bg-zinc-50 dark:bg-zinc-800/50 z-10 border-r border-zinc-200 dark:border-zinc-700">
                                {{ __('Estudiante') }}
                            </th>
                            @if($hasEjeGrouping && $groupedByEje)
                                @foreach($groupedByEje as $ejeName => $ejeIndicators)
                                    <th class="px-2 py-2 text-center font-bold text-xs text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-900/20 border-l-2 border-purple-300 dark:border-purple-700" colspan="{{ count($ejeIndicators) }}">
                                        {{ $ejeName }}
                                    </th>
                                @endforeach
                            @else
                                @foreach($qualitativeIndicators as $ind)
                                    <th class="px-3 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400 min-w-[120px]" title="{{ $ind['description'] ?? $ind['name'] }}">
                                        <span class="text-[11px] font-bold block truncate max-w-[130px] mx-auto" title="{{ $ind['description'] ?? $ind['name'] }}">{{ $ind['name'] }}</span>
                                        @if($ind['description'] ?? null)
                                            <span class="text-[8px] text-zinc-400 block truncate max-w-[130px] mx-auto mt-0.5" title="{{ $ind['description'] }}">{{ Str::limit($ind['description'], 50) }}</span>
                                        @endif
                                    </th>
                                @endforeach
                            @endif
                            <th class="px-3 py-3 text-center font-bold text-blue-700 dark:text-blue-300 min-w-[80px] bg-blue-50 dark:bg-blue-900/20 border-l-2 border-blue-300 dark:border-blue-700">
                                <span class="text-[11px]">{{ __('Promedio') }}</span>
                            </th>
                        </tr>
                        @if($hasEjeGrouping && $groupedByEje)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
                                <th class="px-4 py-2 text-left text-[10px] font-semibold text-zinc-500 sticky left-0 bg-white dark:bg-zinc-900 z-10 border-r border-zinc-200 dark:border-zinc-700">
                                    {{ __('Indicador') }}
                                </th>
                                @foreach($groupedByEje as $ejeIndicators)
                                    @foreach($ejeIndicators as $ind)
                                        <th class="px-2 py-2 text-center text-[10px] font-semibold text-zinc-500 min-w-[140px]">
                                            {{ $ind['name'] }}
                                        </th>
                                    @endforeach
                                @endforeach
                                <th class="bg-blue-50 dark:bg-blue-900/20 border-l-2 border-blue-300 dark:border-blue-700"></th>
                            </tr>
                        @endif
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($studentPaginator as $student)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                <td class="px-4 py-2.5 font-medium text-zinc-900 dark:text-zinc-100 text-xs sticky left-0 bg-white dark:bg-zinc-900 z-10 border-r border-zinc-200 dark:border-zinc-700">
                                    {{ $student->user?->full_name ?? $student->user?->lastname . ' ' . $student->user?->name }}
                                    <span class="block text-[10px] font-mono text-zinc-400">{{ $student->student_code }}</span>
                                </td>
                                @if($hasEjeGrouping && $groupedByEje)
                                    @foreach($groupedByEje as $ejeIndicators)
                                        @foreach($ejeIndicators as $ind)
                                            @php $key = $student->id . '_' . $ind['id']; @endphp
                                            <td class="px-2 py-2 text-center">
                                                @if($this->isGradingOpen())
                                                    <select wire:change="saveQualitativeGrade({{ $student->id }}, {{ $ind['id'] }}, $event.target.value)"
                                                            class="w-full px-1 py-1 border border-zinc-200 dark:border-zinc-600 rounded text-[10px] font-bold text-center bg-white dark:bg-zinc-800 focus:outline-2 focus:outline-blue-500
                                                                {{ match($qualitativeGrades[$key] ?? null) {
                                                                    'S' => 'text-emerald-700 bg-emerald-50 border-emerald-300',
                                                                    'F' => 'text-blue-700 bg-blue-50 border-blue-300',
                                                                    'O' => 'text-amber-700 bg-amber-50 border-amber-300',
                                                                    'N' => 'text-red-700 bg-red-50 border-red-300',
                                                                    default => 'text-zinc-400',
                                                                } }}">
                                                        <option value="" {{ ($qualitativeGrades[$key] ?? null) === null ? 'selected' : '' }}>—</option>
                                                        <option value="S" {{ ($qualitativeGrades[$key] ?? null) === 'S' ? 'selected' : '' }}>S - Siempre</option>
                                                        <option value="F" {{ ($qualitativeGrades[$key] ?? null) === 'F' ? 'selected' : '' }}>F - Frecuentemente</option>
                                                        <option value="O" {{ ($qualitativeGrades[$key] ?? null) === 'O' ? 'selected' : '' }}>O - Ocasionalmente</option>
                                                        <option value="N" {{ ($qualitativeGrades[$key] ?? null) === 'N' ? 'selected' : '' }}>N - Nunca</option>
                                                    </select>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold
                                                        {{ match($qualitativeGrades[$key] ?? null) {
                                                            'S' => 'text-emerald-700 bg-emerald-50',
                                                            'F' => 'text-blue-700 bg-blue-50',
                                                            'O' => 'text-amber-700 bg-amber-50',
                                                            'N' => 'text-red-700 bg-red-50',
                                                            default => 'text-zinc-300',
                                                        } }}">
                                                        {{ $qualitativeGrades[$key] ?? '—' }}
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                    @endforeach
                                @else
                                    @foreach($qualitativeIndicators as $ind)
                                        @php $key = $student->id . '_' . $ind['id']; @endphp
                                        <td class="px-2 py-2 text-center">
                                            @if($isReading)
                                                @if($this->isGradingOpen())
                                                    <input type="number" min="1" max="10" step="1"
                                                           value="{{ $qualitativeGrades[$key] ?? '' }}"
                                                           wire:change="saveQualitativeGrade({{ $student->id }}, {{ $ind['id'] }}, $event.target.value)"
                                                           class="w-14 px-1 py-1 border border-zinc-200 dark:border-zinc-600 rounded text-xs font-bold text-center bg-white dark:bg-zinc-800 focus:outline-2 focus:outline-blue-500
                                                               {{ match(true) {
                                                                   ($qualitativeGrades[$key] ?? null) !== null && $qualitativeGrades[$key] >= 9 => 'text-emerald-700 bg-emerald-50 border-emerald-300',
                                                                   ($qualitativeGrades[$key] ?? null) !== null && $qualitativeGrades[$key] >= 7 => 'text-blue-700 bg-blue-50 border-blue-300',
                                                                   ($qualitativeGrades[$key] ?? null) !== null && $qualitativeGrades[$key] >= 5 => 'text-amber-700 bg-amber-50 border-amber-300',
                                                                   ($qualitativeGrades[$key] ?? null) !== null && $qualitativeGrades[$key] >= 3 => 'text-orange-700 bg-orange-50 border-orange-300',
                                                                   ($qualitativeGrades[$key] ?? null) !== null && $qualitativeGrades[$key] >= 1 => 'text-red-700 bg-red-50 border-red-300',
                                                                   default => 'text-zinc-400',
                                                               } }}"
                                                           placeholder="1-10" />
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold
                                                        {{ match(true) {
                                                            ($qualitativeGrades[$key] ?? null) !== null && $qualitativeGrades[$key] >= 9 => 'text-emerald-700 bg-emerald-50',
                                                            ($qualitativeGrades[$key] ?? null) !== null && $qualitativeGrades[$key] >= 7 => 'text-blue-700 bg-blue-50',
                                                            ($qualitativeGrades[$key] ?? null) !== null && $qualitativeGrades[$key] >= 5 => 'text-amber-700 bg-amber-50',
                                                            ($qualitativeGrades[$key] ?? null) !== null && $qualitativeGrades[$key] >= 3 => 'text-orange-700 bg-orange-50',
                                                            ($qualitativeGrades[$key] ?? null) !== null && $qualitativeGrades[$key] >= 1 => 'text-red-700 bg-red-50',
                                                            default => 'text-zinc-300',
                                                        } }}">
                                                        {{ $qualitativeGrades[$key] ?? '—' }}
                                                    </span>
                                                @endif
                                            @else
                                                @if($this->isGradingOpen())
                                                    <select wire:change="saveQualitativeGrade({{ $student->id }}, {{ $ind['id'] }}, $event.target.value)"
                                                            class="w-full px-1 py-1 border border-zinc-200 dark:border-zinc-600 rounded text-[10px] font-bold text-center bg-white dark:bg-zinc-800 focus:outline-2 focus:outline-blue-500
                                                                {{ match($qualitativeGrades[$key] ?? null) {
                                                                    'S' => 'text-emerald-700 bg-emerald-50 border-emerald-300',
                                                                    'F' => 'text-blue-700 bg-blue-50 border-blue-300',
                                                                    'O' => 'text-amber-700 bg-amber-50 border-amber-300',
                                                                    'N' => 'text-red-700 bg-red-50 border-red-300',
                                                                    default => 'text-zinc-400',
                                                                } }}">
                                                        <option value="" {{ ($qualitativeGrades[$key] ?? null) === null ? 'selected' : '' }}>—</option>
                                                        <option value="S" {{ ($qualitativeGrades[$key] ?? null) === 'S' ? 'selected' : '' }}>S - Siempre</option>
                                                        <option value="F" {{ ($qualitativeGrades[$key] ?? null) === 'F' ? 'selected' : '' }}>F - Frecuentemente</option>
                                                        <option value="O" {{ ($qualitativeGrades[$key] ?? null) === 'O' ? 'selected' : '' }}>O - Ocasionalmente</option>
                                                        <option value="N" {{ ($qualitativeGrades[$key] ?? null) === 'N' ? 'selected' : '' }}>N - Nunca</option>
                                                    </select>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold
                                                        {{ match($qualitativeGrades[$key] ?? null) {
                                                            'S' => 'text-emerald-700 bg-emerald-50',
                                                            'F' => 'text-blue-700 bg-blue-50',
                                                            'O' => 'text-amber-700 bg-amber-50',
                                                            'N' => 'text-red-700 bg-red-50',
                                                            default => 'text-zinc-300',
                                                        } }}">
                                                        {{ $qualitativeGrades[$key] ?? '—' }}
                                                    </span>
                                                @endif
                                            @endif
                                        </td>
                                    @endforeach
                                @endif
                                @php $avg = $this->calculateQualitativeAverage($student->id); @endphp
                                <td class="px-3 py-2.5 text-center font-bold text-sm border-l-2 border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20
                                    {{ match($avg) {
                                        'A+' => 'text-emerald-700',
                                        'A-' => 'text-emerald-600',
                                        'B+' => 'text-blue-700',
                                        'B-' => 'text-blue-600',
                                        'C+' => 'text-amber-600',
                                        'C-' => 'text-amber-700',
                                        'D+' => 'text-orange-600',
                                        'D-' => 'text-orange-700',
                                        'E+' => 'text-red-600',
                                        'E-' => 'text-red-700',
                                        default => 'text-zinc-300',
                                    } }}">
                                    {{ $avg ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($qualitativeIndicators) + 2 }}" class="px-4 py-12 text-center">
                                    <flux:icon.archive-box class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                                    <flux:text variant="subtle" class="text-sm">{{ __('No hay estudiantes matriculados.') }}</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $studentPaginator->links() }}</div>

            @if($isReading)
                <div class="mt-4 flex items-center gap-4 text-[10px] text-zinc-400">
                    <span>{{ __('Valores numericos del 1 al 10') }}</span>
                    <span class="border-l border-zinc-300 dark:border-zinc-600 pl-4 ml-2"></span>
                    <span class="font-bold text-emerald-600">A+</span> = 9.01-10
                    <span class="font-bold text-emerald-600">A-</span> = 8.01-9
                    <span class="font-bold text-blue-600">B+</span> = 7.01-8
                    <span class="font-bold text-blue-600">B-</span> = 6.01-7
                    <span class="font-bold text-amber-600">C+</span> = 5.01-6
                    <span class="font-bold text-amber-700">C-</span> = 4.01-5
                    <span class="font-bold text-orange-600">D+</span> = 3.01-4
                    <span class="font-bold text-orange-700">D-</span> = 2.01-3
                    <span class="font-bold text-red-600">E+</span> = 1.01-2
                    <span class="font-bold text-red-700">E-</span> = 1 o menos
                </div>
            @else
                <div class="mt-4 flex items-center gap-4 text-[10px] text-zinc-400">
                    <span class="font-bold text-emerald-600">S</span> = {{ __('Siempre') }}
                    <span class="font-bold text-blue-600">F</span> = {{ __('Frecuentemente') }}
                    <span class="font-bold text-amber-600">O</span> = {{ __('Ocasionalmente') }}
                    <span class="font-bold text-red-600">N</span> = {{ __('Nunca') }}
                    <span class="border-l border-zinc-300 dark:border-zinc-600 pl-4 ml-2"></span>
                    <span class="font-bold text-emerald-600">A+</span> = 35-36
                    <span class="font-bold text-emerald-600">A-</span> = 33-34
                    <span class="font-bold text-blue-600">B+</span> = 30-32
                    <span class="font-bold text-blue-600">B-</span> = 27-29
                    <span class="font-bold text-amber-600">C+</span> = 20-26
                    <span class="font-bold text-amber-700">C-</span> = 18-19
                    <span class="font-bold text-orange-600">D+</span> = 15-17
                    <span class="font-bold text-orange-700">D-</span> = 13-14
                    <span class="font-bold text-red-600">E+</span> = 11-12
                    <span class="font-bold text-red-700">E-</span> = 10 o menos
                </div>
            @endif
        @elseif($selectedTrimesterId == -1)
            <div class="text-center py-8 text-zinc-400">
                <flux:icon.book-open class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
                <p class="text-base font-semibold">{{ __('Las asignaturas cualitativas no tienen supletorio') }}</p>
                <p class="text-sm text-zinc-400 mt-1">{{ __('Seleccione un trimestre para calificar') }}</p>
            </div>
        @else
            <div class="text-center py-8 text-zinc-400">
                <flux:icon.archive-box class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
                <p class="text-base font-semibold">{{ __('No hay indicadores configurados') }}</p>
            </div>
        @endif
    </div>
</div>
