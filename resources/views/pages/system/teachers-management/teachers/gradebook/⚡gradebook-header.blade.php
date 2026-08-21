<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Libro de Calificaciones') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Gestion de calificaciones por asignatura y grado') }}</flux:text>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Libro de Calificaciones') }}</span>
    </nav>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div>
            <flux:label>{{ __('Asignatura') }}</flux:label>
            <flux:select wire:model.live="selectedSubjectId" wire:key="subject-select">
                <option value="0" @selected(!$selectedSubjectId)>{{ __('Seleccione asignatura') }}</option>
                @foreach($subjects as $subject)
                    <option value="{{ $subject['id'] }}" @selected((int) $selectedSubjectId === (int) $subject['id'])>{{ $subject['subject_name'] }}</option>
                @endforeach
            </flux:select>
        </div>
        <div>
            <flux:label>{{ __('Grado') }}</flux:label>
            <flux:select wire:model.live="selectedGradeId" wire:key="grade-select-{{ $selectedSubjectId ?? 'none' }}">
                <option value="0" @selected(!$selectedGradeId)>{{ __('Seleccione un grado') }}</option>
                @foreach($grades as $grade)
                    <option value="{{ $grade['id'] }}" @selected((int) $selectedGradeId === (int) $grade['id'])>{{ $grade['grade_name'] }} {{ $grade['section'] ?? '' }}</option>
                @endforeach
            </flux:select>
        </div>
        <div>
            <flux:label>{{ __('Trimestre') }}</flux:label>
            <div class="flex gap-1 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-1">
                @foreach($trimesters as $trimester)
                    <button wire:click="$set('selectedTrimesterId', {{ $trimester['id'] }})"
                            class="flex-1 px-3 py-1.5 rounded-md text-xs font-semibold transition
                                   {{ $selectedTrimesterId == $trimester['id']
                                       ? 'bg-blue-600 text-white shadow-sm'
                                       : 'text-zinc-600 dark:text-zinc-400 hover:bg-white dark:hover:bg-zinc-700' }}">
                        {{ $trimester['trimester_name'] }}
                    </button>
                @endforeach
                @if(!$this->isQualitativeSubject())
                <button wire:click="$set('selectedTrimesterId', -1)"
                        class="flex-1 px-3 py-1.5 rounded-md text-xs font-semibold transition
                               {{ $selectedTrimesterId == -1
                                   ? 'bg-blue-600 text-white shadow-sm'
                                   : 'text-zinc-600 dark:text-zinc-400 hover:bg-white dark:hover:bg-zinc-700' }}">
                    {{ __('Supletorio') }}
                </button>
                @endif
            </div>
        </div>
    </div>

    @if($selectedSubjectId && $selectedGradeId)
        <div class="flex items-center gap-4 mb-4 px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl">
            <div class="flex-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->getSubjectName() }}</h2>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $this->getGradeName() }}</span>
                    <span class="text-sm text-zinc-500">{{ $this->getShiftName() }}</span>
                    <span class="text-sm text-zinc-500">{{ $this->getStudentCount() }} {{ __('estudiantes') }}</span>
                </div>
            </div>
            @if(!$this->isQualitativeSubject())
                @if($gradingScheme && $selectedTrimesterId != -1)
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">{{ __('Esquema:') }}</span>
                        <span class="px-2.5 py-0.5 bg-white dark:bg-zinc-800 rounded-full text-xs font-bold text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                            {{ __('Formativo') }} {{ number_format($gradingScheme->formative_percentage, 2) }}%
                        </span>
                        <span class="px-2.5 py-0.5 bg-white dark:bg-zinc-800 rounded-full text-xs font-bold text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                            {{ __('Examen') }} {{ number_format($gradingScheme->exam_percentage, 2) }}%
                        </span>
                        <span class="px-2.5 py-0.5 bg-white dark:bg-zinc-800 rounded-full text-xs font-bold text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                            {{ __('Proyecto') }} {{ number_format($gradingScheme->project_percentage, 2) }}%
                        </span>
                    </div>
                @endif
                @if($selectedTrimesterId != -1)
                    <div class="flex items-center gap-2 flex-wrap">
                        <flux:button size="sm" variant="primary" icon="printer"
                                     href="{{ route('admin.summaries.gradebook.pdf.print-formative', [
                                         'subject_id' => $selectedSubjectId,
                                         'grade_id' => $selectedGradeId,
                                         'trimester_id' => $selectedTrimesterId,
                                     ]) }}"
                                     title="{{ __('Imprimir notas formativas') }}">
                            {{ __('Imprimir Formativas') }}
                        </flux:button>
                        <flux:button size="sm" variant="filled" color="emerald" icon="printer"
                                     href="{{ route('admin.summaries.gradebook.pdf.print-summative', [
                                         'subject_id' => $selectedSubjectId,
                                         'grade_id' => $selectedGradeId,
                                         'trimester_id' => $selectedTrimesterId,
                                     ]) }}"
                                     title="{{ __('Imprimir notas sumativas') }}">
                            {{ __('Imprimir Sumativas') }}
                        </flux:button>
                    </div>
                @endif
                @if($selectedTrimesterId == ($trimesters[2]['id'] ?? null))
                    <div class="flex items-center gap-2 flex-wrap">
                        <flux:button size="sm" variant="filled" color="blue" icon="document-chart-bar"
                                     href="{{ route('admin.summaries.gradebook.pdf.subject-annual-report', [
                                         'subject_id' => $selectedSubjectId,
                                         'grade_id' => $selectedGradeId,
                                     ]) }}"
                                     title="{{ __('Informe anual de la asignatura') }}">
                            {{ __('Informe Anual') }}
                        </flux:button>
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>
