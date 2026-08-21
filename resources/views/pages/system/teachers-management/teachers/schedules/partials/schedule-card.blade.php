{{-- Schedule Card: Individual class card --}}
@props(['horario', 'colores', 'yearId'])

<div class="relative border-l-4 {{ $colores['border'] }} {{ $colores['bg'] }} rounded-xl p-4 transition-all hover-shadow-md
    {{ !$horario->is_active ? 'opacity-50' : '' }}">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-bold {{ $colores['text'] }} font-mono">
            {{ $horario->start_time->format('H:i') }} – {{ $horario->end_time->format('H:i') }}
        </span>
        @if(in_array($horario->schedule_type, ['EVALUATION', 'MAKEUP']))
            @if($horario->calendarday_id && $horario->calendarDay)
                <span class="text-[10px] font-bold {{ $colores['text'] }} {{ $colores['bg'] }} px-2 py-0.5 rounded-full">
                    {{ $horario->calendarDay->date->format('d/m/Y') }}
                </span>
            @else
                <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-2 py-0.5 rounded-full">
                    Sin fecha
                </span>
            @endif
        @endif
    </div>

    {{-- Subject --}}
    <h4 class="text-sm font-bold text-zinc-900 dark:text-white mb-1">
        {{ $horario->subject->subject_name }}
    </h4>

    {{-- Details --}}
    <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-2">
        {{ $horario->grade->grade_name }} / {{ $horario->grade->section }}
        · {{ optional($horario->grade->nivel->shift)->shift_name ?? 'N/A' }}
    </p>

    {{-- Meta Info --}}
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <span class="inline-flex items-center gap-1 text-[11px] font-medium text-zinc-500 dark:text-zinc-400">
            <flux:icon.folder />
            {{ optional($horario->subject->area)->area_name ?? 'N/A' }}
        </span>
        <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
            <flux:icon.user-group />
            {{ $this->getStudentsCountForGrades([$horario->grade_id]) }} {{ __('alumnos') }}
        </span>
        @if($horario->trimester_id)
            <span class="text-[11px] font-medium text-blue-600 dark:text-blue-400">
                Trim: {{ $horario->trimester->trimester_name ?? 'N/A' }}
            </span>
        @endif
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
            {{ $horario->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
            {{ $horario->is_active ? 'ACTIVO' : 'INACTIVO' }}
        </span>
    </div>

    {{-- Action Buttons --}}
    <div class="flex items-center gap-1.5">
        <a href="{{ route('system.identity.students.import', $horario->grade_id) }}" wire:navigate
           class="w-7 h-7 rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 flex items-center justify-center text-zinc-400 hover:border-emerald-500 hover:text-emerald-600 transition"
           title="{{ __('Importar estudiantes') }}">
            <flux:icon.user-plus class="size-3.5" />
        </a>
        <button wire:click="openAttendanceModal({{ $horario->id }})"
           class="w-7 h-7 rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 flex items-center justify-center text-zinc-400 hover:border-blue-500 hover:text-blue-600 transition"
           title="{{ __('Tomar asistencia') }}">
            <flux:icon.clipboard-document-check class="size-3.5" />
        </button>
        <button wire:click="openQuickGradesModal({{ $horario->id }})"
           class="w-7 h-7 rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 flex items-center justify-center text-zinc-400 hover:border-amber-500 hover:text-amber-600 transition"
           title="{{ __('Registrar calificaciones') }}">
            <flux:icon.document-check class="size-3.5" />
        </button>
        @if(in_array($horario->schedule_type, ['EVALUATION', 'MAKEUP']))
            <button wire:click="openEvaluationModal({{ $horario->id }})"
                    class="w-7 h-7 rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 flex items-center justify-center text-zinc-400 hover:border-purple-500 hover:text-purple-600 transition"
                    title="{{ __('Asignar fecha') }}">
                <flux:icon.calendar class="size-3.5" />
            </button>
        @endif
        <button wire:click="deleteSchedule({{ $horario->id }})"
                wire:confirm="{{ __('Eliminar este horario?') }}"
                class="w-7 h-7 rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 flex items-center justify-center text-zinc-400 hover:border-red-500 hover:text-red-600 transition"
                title="{{ __('Eliminar') }}">
            <flux:icon.trash class="size-3.5" />
        </button>
    </div>
</div>
