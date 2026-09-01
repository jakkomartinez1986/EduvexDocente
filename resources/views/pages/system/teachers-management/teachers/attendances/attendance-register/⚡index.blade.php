<?php

declare(strict_types=1);

use App\Models\Identity\Users\Student;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Services\AcademicYearService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Registro de Asistencia')] class extends Component {
    private const PAGE_SIZE = 12;

    public ?int $yearId = null;
    public ?int $selectedGradeId = null;
    public ?int $selectedSubjectId = null;
    public ?int $selectedScheduleId = null;
    public string $attendanceDate = '';

    public array $allSchedules = [];
    public array $grades = [];
    public array $subjects = [];
    public array $weekdaySchedules = [];

    public array $students = [];
    public array $statuses = [];
    public array $savedStatuses = [];

    public string $classtopic = '';
    public string $observation = '';
    public array $observations = [];

    public int $studentPage = 0;

    public function mount(): void
    {
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        $this->attendanceDate = now()->format('Y-m-d');
        $this->loadTeacherData();
    }

    protected function loadTeacherData(): void
    {
        $teacherId = auth()->user()->teacher?->id;

        $this->allSchedules = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $this->yearId)
            ->where('schedule_type', 'OFFICIAL')
            ->with('grade.nivel.shift', 'subject')
            ->get()
            ->toArray();

        $this->loadGrades();
        $this->loadSubjectsForGrade();

        if (count($this->grades) === 1 && ! $this->selectedGradeId) {
            $this->selectedGradeId = $this->grades[0]['id'];
            $this->loadSubjectsForGrade();
        }

        if (count($this->subjects) === 1 && ! $this->selectedSubjectId) {
            $this->selectedSubjectId = $this->subjects[0]['id'];
        }

        $this->refreshForSelection();
    }

    public function updatedSelectedGradeId(): void
    {
        $this->selectedSubjectId = null;
        $this->selectedScheduleId = null;
        $this->loadSubjectsForGrade();

        if (count($this->subjects) === 1) {
            $this->selectedSubjectId = $this->subjects[0]['id'];
        }

        $this->refreshForSelection();
    }

    public function updatedSelectedSubjectId(): void
    {
        $this->selectedScheduleId = null;
        $this->refreshForSelection();
    }

    public function updatedAttendanceDate(): void
    {
        $this->selectedGradeId = null;
        $this->selectedSubjectId = null;
        $this->selectedScheduleId = null;

        $this->loadGrades();
        $this->loadSubjectsForGrade();

        if (count($this->grades) === 1) {
            $this->selectedGradeId = $this->grades[0]['id'];
            $this->loadSubjectsForGrade();
        }

        if (count($this->subjects) === 1) {
            $this->selectedSubjectId = $this->subjects[0]['id'];
        }

        $this->refreshForSelection();
    }

    public function updatedSelectedScheduleId(): void
    {
        $this->studentPage = 0;
        $this->loadStudents();
    }

    protected function loadGrades(): void
    {
        $weekday = $this->getWeekdayCode($this->attendanceDate);

        if (! $weekday) {
            $this->grades = [];

            return;
        }

        $this->grades = collect($this->allSchedules)
            ->where('day', $weekday)
            ->pluck('grade')
            ->unique('id')
            ->values()
            ->all();
    }

    protected function loadSubjectsForGrade(): void
    {
        $weekday = $this->getWeekdayCode($this->attendanceDate);

        if (! $this->selectedGradeId || ! $weekday) {
            $this->subjects = [];

            return;
        }

        $this->subjects = collect($this->allSchedules)
            ->where('grade_id', $this->selectedGradeId)
            ->where('day', $weekday)
            ->pluck('subject')
            ->unique('id')
            ->values()
            ->all();
    }

    protected function refreshForSelection(): void
    {
        $this->studentPage = 0;
        $this->weekdaySchedules = [];
        $this->students = [];
        $this->statuses = [];
        $this->savedStatuses = [];
        $this->classtopic = '';
        $this->observation = '';
        $this->observations = [];

        if (! $this->selectedGradeId || ! $this->selectedSubjectId || ! $this->attendanceDate) {
            return;
        }

        $weekday = $this->getWeekdayCode($this->attendanceDate);

        if (! $weekday) {
            return;
        }

        $this->weekdaySchedules = collect($this->allSchedules)
            ->filter(fn ($s) => $s['grade_id'] == $this->selectedGradeId
                && $s['subject_id'] == $this->selectedSubjectId
                && $s['day'] === $weekday)
            ->sortBy('start_time')
            ->values()
            ->all();

        if (count($this->weekdaySchedules) === 0) {
            return;
        }

        $scheduleIds = array_column($this->weekdaySchedules, 'id');

        if (! $this->selectedScheduleId || ! in_array($this->selectedScheduleId, $scheduleIds)) {
            $this->selectedScheduleId = $scheduleIds[0];
        }

        $this->loadStudents();
    }

    protected function getWeekdayCode(string $date): ?string
    {
        $dayOfWeek = \Carbon\Carbon::parse($date)->dayOfWeek;

        return [
            1 => 'LUNES',
            2 => 'MARTES',
            3 => 'MIERCOLES',
            4 => 'JUEVES',
            5 => 'VIERNES',
            6 => 'SABADO',
        ][$dayOfWeek] ?? null;
    }

    protected function loadStudents(): void
    {
        $this->studentPage = 0;
        $this->students = [];
        $this->statuses = [];
        $this->savedStatuses = [];
        $this->classtopic = '';
        $this->observation = '';
        $this->observations = [];

        if (! $this->selectedScheduleId || ! $this->attendanceDate) {
            return;
        }

        $students = Student::whereHas('enrollments', function ($q) {
            $q->where('grade_id', $this->selectedGradeId)
              ->where('year_id', $this->yearId);
        })->with('user')->get();

        $existing = Attendance::where('class_schedule_id', $this->selectedScheduleId)
            ->where('date', $this->attendanceDate)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $this->students = $students->map(fn ($s) => [
            'id'   => $s->id,
            'name' => $s->user->full_name ?? trim(($s->user->lastname ?? '') . ' ' . ($s->user->name ?? '')),
            'code' => $s->student_code,
        ])->values()->toArray();

        $this->statuses = collect($this->students)
            ->mapWithKeys(function ($s) use ($existing) {
                $status = $existing->get($s['id'])?->status;

                return [$s['id'] => $status ? trim($status) : 'P'];
            })
            ->toArray();

        $this->savedStatuses = collect($this->students)
            ->mapWithKeys(function ($s) use ($existing) {
                $status = $existing->get($s['id'])?->status;

                return [$s['id'] => $status ? trim($status) : null];
            })
            ->toArray();

        $this->observations = collect($this->students)
            ->mapWithKeys(fn ($s) => [$s['id'] => $existing->get($s['id'])?->observation ?? ''])
            ->toArray();

        $existingObs = ClassObservation::where('class_schedule_id', $this->selectedScheduleId)
            ->where('observation_date', $this->attendanceDate)
            ->first();

        $this->classtopic = $existingObs?->classtopic ?? '';
        $this->observation = $existingObs?->observation ?? '';
    }

    public function getPaginatedStudents(): array
    {
        $page = array_slice($this->students, $this->studentPage * self::PAGE_SIZE, self::PAGE_SIZE);

        return array_map(fn ($s) => [
            'id'     => $s['id'],
            'name'   => $s['name'],
            'code'   => $s['code'],
            'status' => $this->statuses[$s['id']] ?? 'P',
            'saved'  => $this->savedStatuses[$s['id']] ?? 'P',
        ], $page);
    }

    public function getTotalPages(): int
    {
        return max(1, (int) ceil(count($this->students) / self::PAGE_SIZE));
    }

    public function prevPage(): void
    {
        if ($this->studentPage > 0) {
            $this->studentPage--;
        }
    }

    public function nextPage(): void
    {
        if ($this->studentPage < $this->getTotalPages() - 1) {
            $this->studentPage++;
        }
    }

    public function goToPage(int $page): void
    {
        $this->studentPage = max(0, min($page, $this->getTotalPages() - 1));
    }

    public function setStatus(int $studentId, string $status): void
    {
        $this->statuses[$studentId] = $status;
    }

    public function markAllPresent(): void
    {
        $this->statuses = collect($this->students)
            ->mapWithKeys(fn ($s) => [$s['id'] => 'P'])
            ->toArray();
    }

    public function countStatus(string $status): int
    {
        return collect($this->statuses)->filter(fn ($s) => $s === $status)->count();
    }

    public function pendingCount(): int
    {
        $count = 0;

        foreach ($this->students as $student) {
            $current = $this->statuses[$student['id']] ?? 'P';
            $saved = $this->savedStatuses[$student['id']] ?? 'P';

            if ($current !== $saved) {
                $count++;
            }
        }

        return $count;
    }

    public function getSubjectName(): string
    {
        $schedule = collect($this->allSchedules)->firstWhere('subject_id', $this->selectedSubjectId);

        return $schedule['subject']['subject_name'] ?? '';
    }

    public function getGradeName(): string
    {
        $schedule = collect($this->allSchedules)->firstWhere('grade_id', $this->selectedGradeId);

        if (! $schedule || ! $schedule['grade']) {
            return '';
        }

        return ($schedule['grade']['grade_name'] ?? '') . ' ' . ($schedule['grade']['section'] ?? '');
    }

    public function getShiftName(): string
    {
        $schedule = collect($this->allSchedules)->firstWhere('grade_id', $this->selectedGradeId);

        return $schedule['grade']['nivel']['shift']['shift_name'] ?? '';
    }

    public function getWeekdayLabel(): string
    {
        $weekday = $this->getWeekdayCode($this->attendanceDate);

        return match ($weekday) {
            'LUNES' => 'Lunes',
            'MARTES' => 'Martes',
            'MIERCOLES' => 'Miércoles',
            'JUEVES' => 'Jueves',
            'VIERNES' => 'Viernes',
            'SABADO' => 'Sabado',
            default => '',
        };
    }

    public function saveAttendance(): void
    {
        if (! $this->selectedScheduleId || ! $this->attendanceDate) {
            Flux::toast(variant: 'warning', text: __('Seleccione el horario y la fecha.'));

            return;
        }

        if (count($this->students) === 0) {
            Flux::toast(variant: 'warning', text: __('No hay estudiantes para registrar.'));

            return;
        }

        if (trim($this->classtopic) === '') {
            Flux::toast(variant: 'warning', text: __('El tema de la clase es obligatorio.'));

            return;
        }

        $calendarDay = CalendarDay::where('date', $this->attendanceDate)->first();

        $observation = ClassObservation::updateOrCreate(
            [
                'class_schedule_id' => $this->selectedScheduleId,
                'observation_date'  => $this->attendanceDate,
            ],
            [
                'teacher_id'        => auth()->user()->teacher?->id,
                'year_id'           => $this->yearId,
                'classtopic'        => $this->classtopic,
                'observation'       => $this->observation ?: 'Tema de Clase.',
                'class_observation' => $this->observation,
            ]
        );

        foreach ($this->students as $student) {
            $status = trim((string) ($this->statuses[$student['id']] ?? 'P'));

            if ($status === 'P' || $status === '') {
                Attendance::where('class_schedule_id', $this->selectedScheduleId)
                    ->where('student_id', $student['id'])
                    ->where('date', $this->attendanceDate)
                    ->forceDelete();

                continue;
            }

            Attendance::updateOrCreate(
                [
                    'class_schedule_id' => $this->selectedScheduleId,
                    'student_id'        => $student['id'],
                    'date'              => $this->attendanceDate,
                ],
                [
                    'class_observation_id' => $observation->id,
                    'calendarday_id'       => $calendarDay?->id,
                    'year_id'              => $this->yearId,
                    'status'               => $status,
                    'observation'          => trim((string) ($this->observations[$student['id']] ?? '')) ?: null,
                    'recorded_by'          => auth()->id(),
                ]
            );
        }

        $this->loadStudents();
        Flux::toast(variant: 'success', text: __('Asistencia guardada correctamente.'));
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Registro de Asistencia') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Registre la asistencia de los estudiantes por materia, grado y fecha.') }}</flux:text>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Registro de Asistencia') }}</span>
    </nav>

    {{-- Selectors --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6 p-5 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div>
            <flux:label>{{ __('Grado') }}</flux:label>
            <flux:select wire:model.live="selectedGradeId" placeholder="{{ __('Seleccione grado') }}">
                @foreach($grades as $grade)
                    <flux:select.option value="{{ $grade['id'] }}">{{ $grade['grade_name'] }} / {{ $grade['section']}} - {{ $grade['nivel']['shift']['shift_name'] ?? '' }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div>
            <flux:label>{{ __('Asignatura') }}</flux:label>
            <flux:select wire:model.live="selectedSubjectId" placeholder="{{ __('Seleccione asignatura') }}">
                @foreach($subjects as $subject)
                    <flux:select.option value="{{ $subject['id'] }}">{{ $subject['subject_name'] }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div>
            <flux:label>{{ __('Fecha') }}</flux:label>
            <span class="inline-flex items-center gap-2 h-10 w-full px-3 rounded-lg text-sm font-semibold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                <flux:icon.calendar-days class="size-4" />
                {{ \Carbon\Carbon::parse($attendanceDate)->format('d/m/Y') }}
            </span>
        </div>
    </div>

    @if(count($grades) === 0)
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700 border-dashed">
            <flux:icon.calendar-days class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('No hay clase este dia') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('No se encontro un horario para la fecha seleccionada.') }}</p>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Seleccione otra fecha para registrar asistencia.') }}</p>
        </div>
    @elseif(! $selectedGradeId || ! $selectedSubjectId)
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.clipboard-document-check class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('Seleccione un grado y una asignatura para comenzar') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('Use los selectores superiores para filtrar') }}</p>
        </div>
    @elseif(count($weekdaySchedules) === 0)
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700 border-dashed">
            <flux:icon.calendar-days class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('No hay clase este dia') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('No se encontro un horario para la fecha seleccionada.') }}</p>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Verifique la fecha o seleccione otra asignatura/grado.') }}</p>
        </div>
    @else
        @php
            $statusDefs = [
                'P' => ['Presente', 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400', 'bg-emerald-500'],
                'A' => ['Atraso', 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400', 'bg-amber-500'],
                'I' => ['F. Injustificada', 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400', 'bg-red-500'],
                'J' => ['F. Justificada', 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400', 'bg-blue-500'],
                'AI' => ['Ab. Institucional', 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-400', 'bg-purple-500'],
                'AA' => ['Ab. Aula', 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300', 'bg-zinc-400'],
            ];
        @endphp

        {{-- Period / Hora pills --}}
        @if(count($weekdaySchedules) > 1)
            <div class="mb-4">
                <flux:label>{{ __('Periodo / Hora') }}</flux:label>
                <div class="flex flex-wrap gap-2 mt-1.5">
                    @foreach($weekdaySchedules as $schedule)
                        <button wire:click="$set('selectedScheduleId', {{ $schedule['id'] }})"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition
                                {{ $selectedScheduleId == $schedule['id'] ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700 hover:border-blue-500 hover:text-blue-600' }}">
                            {{ \Carbon\Carbon::parse($schedule['start_time'])->format('H:i') }} - {{ \Carbon\Carbon::parse($schedule['end_time'])->format('H:i') }}
                            @if(! empty($schedule['classroom']))
                                <span class="opacity-60">· {{ $schedule['classroom'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Info Header --}}
        <div class="flex items-center gap-4 mb-4 px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl flex-wrap">
            <div class="flex-1 min-w-[200px]">
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->getSubjectName() }}</h2>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $this->getGradeName() }}</span>
                    <span class="text-sm text-zinc-500">{{ $this->getShiftName() }}</span>
                </div>
                <span class="text-xs text-zinc-500 mt-1 block">
                    <span class="font-semibold text-blue-700 dark:text-blue-300">{{ $this->getWeekdayLabel() }}</span>
                    · {{ \Carbon\Carbon::parse($attendanceDate)->format('d/m/Y') }}
                    @if($selectedScheduleId)
                        · {{ \Carbon\Carbon::parse(collect($weekdaySchedules)->firstWhere('id', $selectedScheduleId)['start_time'])->format('H:i') }} - {{ \Carbon\Carbon::parse(collect($weekdaySchedules)->firstWhere('id', $selectedScheduleId)['end_time'])->format('H:i') }}
                    @endif
                </span>
            </div>
            <span class="px-2.5 py-1 bg-white dark:bg-zinc-800 rounded-full text-xs font-bold text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                {{ count($students) }} {{ __('estudiantes') }}
            </span>
            <flux:button variant="primary" wire:click="saveAttendance" :disabled="count($students) === 0" class="whitespace-nowrap">
                <flux:icon.check class="size-4" /> {{ __('Guardar asistencia') }}
            </flux:button>
        </div>

        {{-- Status Summary + Quick Action --}}
        <div class="flex flex-wrap items-center gap-2 mb-4 px-4 py-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 mr-1">{{ __('Resumen') }}</span>
            @foreach($statusDefs as $code => [$label, $activeClasses, $dot])
                <div class="flex items-center gap-1.5 px-2 py-1 rounded bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700" title="{{ __($label) }}">
                    <span class="w-2 h-2 rounded-full {{ $dot }}"></span>
                    <span class="text-[11px] font-bold text-zinc-700 dark:text-zinc-300">{{ $this->countStatus($code) }}</span>
                    <span class="text-[10px] text-zinc-400">{{ $code }}</span>
                </div>
            @endforeach
            <div class="ml-auto flex items-center gap-2">
                @if($this->pendingCount() > 0)
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-[11px] font-semibold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20">
                        <flux:icon.exclamation-circle class="size-3.5" /> {{ $this->pendingCount() }} {{ __('sin guardar') }}
                    </span>
                @endif
                <button wire:click="markAllPresent"
                        class="px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 text-xs font-semibold hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition whitespace-nowrap">
                    {{ __('Marcar todos P') }}
                </button>
            </div>
        </div>

        {{-- Observacion de Clase --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4 p-4 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div>
                <flux:label>{{ __('Tema de clase') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="classtopic" placeholder="{{ __('Tema de la clase...') }}" />
            </div>
            <div>
                <flux:label>{{ __('Observacion') }}</flux:label>
                <flux:input wire:model="observation" placeholder="{{ __('Observacion / detalle...') }}" />
            </div>
        </div>

        {{-- Students List --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-between">
                <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Lista de estudiantes') }}</span>
                @if(count($students) > 0)
                    <span class="text-[10px] font-mono text-zinc-400">
                        {{ __('Mostrando') }} {{ $studentPage * self::PAGE_SIZE + 1 }}–{{ min(($studentPage + 1) * self::PAGE_SIZE, count($students)) }} {{ __('de') }} {{ count($students) }}
                    </span>
                @endif
            </div>

            @forelse($this->getPaginatedStudents() as $student)
                <div class="flex items-center gap-3 px-4 py-2.5 border-b border-zinc-100 dark:border-zinc-700 last:border-b-0 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                    <div class="w-8 h-8 rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center text-xs font-bold text-zinc-500 dark:text-zinc-400 flex-shrink-0">
                        {{ strtoupper(mb_substr($student['name'], 0, 1)) }}{{ strtoupper(mb_substr(trim(explode(' ', $student['name'])[1] ?? ''), 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate flex items-center gap-1.5">
                            {{ $student['name'] }}
                            @if($student['status'] !== $student['saved'])
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block flex-shrink-0" title="{{ __('Cambio sin guardar') }}"></span>
                            @endif
                        </div>
                        <div class="text-[10px] font-mono text-zinc-400">{{ $student['code'] }}</div>
                    </div>
                    <div class="flex border border-zinc-200 dark:border-zinc-600 rounded-lg overflow-hidden flex-shrink-0">
                        @foreach($statusDefs as $code => [$label, $activeClasses, $dot])
                            <button wire:click="setStatus({{ $student['id'] }}, '{{ $code }}')"
                                    class="px-2.5 py-1.5 text-[11px] font-bold transition border-r border-zinc-200 dark:border-zinc-600 last:border-r-0
                                           {{ $student['status'] === $code ? $activeClasses : 'bg-white dark:bg-zinc-800 text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-700' }}"
                                    title="{{ __($label) }}">{{ $code }}</button>
                        @endforeach
                    </div>
                    <div class="w-52 flex-shrink-0">
                        <input type="text"
                               wire:model="observations.{{ $student['id'] }}"
                               placeholder="{{ __('Novedad (opcional)') }}"
                               class="w-full h-8 px-2.5 rounded-lg text-xs border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                </div>
            @empty
                <div class="text-center py-12 text-zinc-400">
                    <flux:icon.user-group class="mx-auto mb-3 size-8 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-sm font-semibold">{{ __('No hay estudiantes matriculados en este grado.') }}</p>
                </div>
            @endforelse

            @if($this->getTotalPages() > 1)
                <div class="flex items-center justify-between px-4 py-3 border-t border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                    <button wire:click="prevPage" {{ $studentPage === 0 ? 'disabled' : '' }}
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 disabled:opacity-30 transition">
                        &lsaquo; {{ __('Anterior') }}
                    </button>
                    <div class="flex items-center gap-1.5">
                        @for($p = 0; $p < $this->getTotalPages(); $p++)
                            <button wire:click="goToPage({{ $p }})"
                                    class="w-8 h-8 rounded-lg text-xs font-bold transition
                                           {{ $p === $studentPage ? 'bg-blue-600 text-white shadow-sm' : 'text-zinc-500 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-700' }}">
                                {{ $p + 1 }}
                            </button>
                        @endfor
                    </div>
                    <button wire:click="nextPage" {{ $studentPage >= $this->getTotalPages() - 1 ? 'disabled' : '' }}
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 disabled:opacity-30 transition">
                        {{ __('Siguiente') }} &rsaquo;
                    </button>
                </div>
            @endif
        </div>

        {{-- Legend --}}
        <div class="flex flex-wrap gap-2 mt-4 px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 self-center mr-1">{{ __('Leyenda:') }}</span>
            @foreach($statusDefs as $code => [$label, $activeClasses, $dot])
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:text-zinc-400">
                    <span class="w-2.5 h-2.5 rounded-sm {{ $dot }}"></span>
                    {{ $code }} = {{ __($label) }}
                </span>
            @endforeach
        </div>

        <div class="mt-4 flex items-center justify-end">
            <flux:button variant="primary" wire:click="saveAttendance" :disabled="count($students) === 0">
                <flux:icon.check class="size-4" /> {{ __('Guardar asistencia') }}
            </flux:button>
        </div>
    @endif
</div>
