<?php

use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Services\AcademicYearService;
use App\Services\TeacherManagement\AttendanceService;
use App\Services\TeacherManagement\ClassScheduleService;
use App\Actions\TeacherManagement\SaveClassScheduleAction;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Horario Docente')] class extends Component {
    public ?int $yearId = null;

    public ?int $teacher_id = null;
    public ?string $scheduleType = null;
    public bool $scheduleTypeSelected = false;

    public ?int $selectedArea = null;
    public ?int $selectedSubject = null;
    public ?int $selectedJornada = null;
    public ?int $selectedNivel = null;
    public ?int $selectedGrade = null;

    public string $day = 'LUNES';
    public string $start_time = '';
    public string $end_time = '';
    public ?string $classroom = null;
    public ?int $trimester_id = null;
    public ?string $evaluacionFecha = null;

    public bool $editMode = false;
    public ?int $scheduleId = null;

    public $allAreas = [];
    public $allJornadas = [];
    public $filteredNiveles = [];
    public $filteredGrados = [];
    public $trimestres = [];

    public bool $showAttendanceModal = false;
    public ?int $attendanceScheduleId = null;
    public string $attendanceDate = '';
    public $attendanceStudents = [];
    public array $attendanceStatuses = [];
    public array $attendanceTimes = [];
    public array $attendanceObservations = [];

    public bool $showTaskModal = false;
    public ?int $taskScheduleId = null;
    public string $taskDate = '';
    public string $taskTopic = '';
    public string $taskObservation = '';

    public function mount(): void
    {
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        $this->allAreas = Area::with('subjects')->orderBy('area_name')->get();
        $this->allJornadas = Shift::where('status', 1)->get();
        $this->trimestres = AcademicPeriod::where('year_id', $this->yearId)->get();

        $userTeacher = auth()->user()->teacher;
        if ($userTeacher) {
            $this->teacher_id = $userTeacher->id;
        }

        $this->attendanceDate = date('Y-m-d');
        $this->taskDate = date('Y-m-d');
    }

    public function updatedSelectedJornada(): void
    {
        $this->selectedNivel = null;
        $this->selectedGrade = null;
        $this->filteredNiveles = Nivel::where('shift_id', $this->selectedJornada)->get();
        $this->filteredGrados = [];
    }

    public function updatedSelectedNivel(): void
    {
        $this->selectedGrade = null;
        $this->filteredGrados = Grade::where('nivel_id', $this->selectedNivel)->get();
    }

    public function selectScheduleType(string $type): void
    {
        $this->scheduleType = $type;
        $this->scheduleTypeSelected = true;

        if (in_array($type, ['EVALUATION', 'MAKEUP'])) {
            $year = app(AcademicYearService::class)->getActiveYear();
            if ($year) {
                $currentTrimester = $year->academicPeriods()->where('status', 1)->first();
                if ($currentTrimester) {
                    $this->trimester_id = $currentTrimester->id;
                }
            }
        } else {
            $this->trimester_id = null;
        }
    }

    public function changeScheduleType(): void
    {
        $this->scheduleTypeSelected = false;
        $this->scheduleType = null;
        $this->trimester_id = null;
        $this->resetForm();
    }

    public function requiresTrimester(): bool
    {
        return in_array($this->scheduleType, ['EVALUATION', 'MAKEUP']);
    }

    public function updatedSelectedArea(): void
    {
        $this->selectedSubject = null;
    }

    protected function rules(): array
    {
        return [
            'selectedArea' => 'required',
            'selectedSubject' => 'required',
            'selectedJornada' => 'required',
            'selectedNivel' => 'required',
            'selectedGrade' => 'required',
            'day' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'selectedArea' => 'area',
            'selectedSubject' => 'asignatura',
            'selectedJornada' => 'jornada',
            'selectedNivel' => 'nivel',
            'selectedGrade' => 'grado',
            'day' => 'día',
            'start_time' => 'hora de inicio',
            'end_time' => 'hora de fin',
        ];
    }

    public function save(): void
    {
        if (! $this->scheduleTypeSelected || ! $this->scheduleType) {
            Flux::toast(variant: 'warning', text: __('Debes seleccionar un tipo de horario primero.'));
            return;
        }

        if ($this->requiresTrimester() && ! $this->trimester_id) {
            Flux::toast(variant: 'warning', text: __('Para horas de evaluación y recuperación debes seleccionar un trimestre.'));
            return;
        }

        $this->validate();

        $year = app(AcademicYearService::class)->getActiveYear();
        $gradeActive = Grade::findOrFail($this->selectedGrade);

        $hasConflict = app(SaveClassScheduleAction::class)->validateIntegralSupport(
            teacherId: $this->teacher_id,
            subjectId: $this->selectedSubject,
            yearId: $year->id,
            gradeId: $this->selectedGrade,
            excludeScheduleId: $this->editMode ? $this->scheduleId : null,
        );

        if ($hasConflict) {
            Flux::toast(variant: 'danger', text: __('Este docente ya tiene asignada la hora de Acompañamiento integral en otro curso. Solo se permite en un curso.'));
            return;
        }

        $data = [
            'year_id'        => $year->id,
            'teacher_id'     => $this->teacher_id,
            'subject_id'     => $this->selectedSubject,
            'grade_id'       => $this->selectedGrade,
            'trimester_id'   => $this->requiresTrimester() ? $this->trimester_id : null,
            'schedule_type'  => $this->scheduleType,
            'day'            => $this->day,
            'start_time'     => $this->start_time,
            'end_time'       => $this->end_time,
            'classroom'      => $this->classroom ?? ($gradeActive->grade_name.' - '.($gradeActive->section ?? '')),
            'is_active'      => true,
        ];

        app(SaveClassScheduleAction::class)->handle($data, $this->editMode ? $this->scheduleId : null);

        if (! $this->editMode) {
            $isIntegralSupport = Subject::find($this->selectedSubject)?->subject_name === 'Acompañamiento integral en el aula';
            if ($isIntegralSupport) {
                app(SaveClassScheduleAction::class)->assignTutorRoleIfNeeded($this->teacher_id);
            }
        }

        Flux::toast(variant: 'success', text: $this->editMode ? __('Horario actualizado correctamente.') : __('Horario creado correctamente.'));
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset([
            'selectedArea', 'selectedSubject', 'selectedJornada',
            'selectedNivel', 'selectedGrade', 'day',
            'start_time', 'end_time', 'classroom', 'editMode', 'scheduleId',
        ]);
        $this->filteredNiveles = [];
        $this->filteredGrados = [];
    }

    public function getTeacherSchedules()
    {
        if (! $this->teacher_id || ! $this->yearId) {
            return collect();
        }

        return app(ClassScheduleService::class)->getTeacherSchedules($this->yearId, $this->teacher_id);
    }

    public function getStudentCountForGrade(int $gradeId): int
    {
        if (! $this->yearId) {
            return 0;
        }

        return app(ClassScheduleService::class)->getStudentCountForGrade($gradeId, $this->yearId);
    }

    public function openAttendanceModal(int $scheduleId): void
    {
        $this->attendanceScheduleId = $scheduleId;
        $this->attendanceDate = date('Y-m-d');

        $this->attendanceStudents = app(AttendanceService::class)->loadStudentsForAttendanceCreate(
            $scheduleId,
            $this->yearId,
        );

        $existing = app(AttendanceService::class)->loadExistingStatusesCreate(
            $scheduleId,
            $this->attendanceDate,
            $this->attendanceStudents,
        );

        $this->attendanceStatuses = $existing['statuses'];
        $this->attendanceTimes = $existing['times'];
        $this->attendanceObservations = $existing['observations'];

        $this->showAttendanceModal = true;
    }

    public function saveAttendance(): void
    {
        if (! $this->attendanceScheduleId || ! $this->yearId) {
            return;
        }

        app(AttendanceService::class)->saveAttendanceCreate(
            scheduleId: $this->attendanceScheduleId,
            date: $this->attendanceDate,
            yearId: $this->yearId,
            userId: auth()->id(),
            statuses: $this->attendanceStatuses,
            observations: $this->attendanceObservations,
        );

        Flux::toast(variant: 'success', text: __('Asistencia registrada correctamente.'));
        $this->showAttendanceModal = false;
    }

    public function openTaskModal(int $scheduleId): void
    {
        $this->taskScheduleId = $scheduleId;
        $this->taskDate = date('Y-m-d');
        $this->taskTopic = '';
        $this->taskObservation = '';
        $this->showTaskModal = true;
    }

    public function saveTask(): void
    {
        if (! $this->taskScheduleId || ! $this->yearId) {
            return;
        }

        ClassObservation::create([
            'class_schedule_id' => $this->taskScheduleId,
            'teacher_id' => $this->teacher_id,
            'year_id' => $this->yearId,
            'observation_date' => $this->taskDate,
            'classtopic' => $this->taskTopic ?: null,
            'observation' => $this->taskObservation,
        ]);

        Flux::toast(variant: 'success', text: __('Tarea registrada correctamente.'));
        $this->showTaskModal = false;
    }

    public function deleteSchedule(int $scheduleId): void
    {
        app(SaveClassScheduleAction::class)->deleteSchedule($scheduleId);
        Flux::toast(variant: 'success', text: __('Horario eliminado correctamente.'));
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Horario Docente') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Crear y gestionar horas academicas') }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('admin.teacher.schedule.timeline') }}" wire:navigate variant="primary">
                <flux:icon.calendar /> {{ __('Horario Docente') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.teacher.schedule.timeline') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Horarios') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Crear') }}</span>
    </nav>

    {{-- Step 1: Schedule Type Selection --}}
    @if(!$scheduleTypeSelected)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-8 text-center max-w-2xl mx-auto">
            <flux:heading size="lg" class="mb-2">{{ __('Selecciona el tipo de horario') }}</flux:heading>
            <flux:text variant="subtle" class="mb-6">{{ __('Elige el tipo de horario que deseas crear') }}</flux:text>

            <div class="grid grid-cols-2 gap-4">
                <button wire:click="selectScheduleType('OFFICIAL')"
                        class="p-6 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 hover:border-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-900/10 transition text-left group">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center text-emerald-600 mb-3 group-hover:scale-110 transition">
                        <flux:icon.book-open />
                    </div>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">{{ __('Horario Oficial') }}</div>
                    <div class="text-xs text-zinc-500">{{ __('Horario regular del ciclo lectivo') }}</div>
                </button>

                <button wire:click="selectScheduleType('EVALUATION')"
                        class="p-6 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 hover:border-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition text-left group">
                    <div class="w-10 h-10 rounded-lg bg-red-50 dark:bg-red-900/20 flex items-center justify-center text-red-600 mb-3 group-hover:scale-110 transition">
                        <flux:icon.document-text />
                    </div>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">{{ __('Horario de Evaluación') }}</div>
                    <div class="text-xs text-zinc-500">{{ __('Sesiones de evaluación trimestral') }}</div>
                </button>

                <button wire:click="selectScheduleType('TEST')"
                        class="p-6 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 hover:border-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/10 transition text-left group">
                    <div class="w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center text-amber-600 mb-3 group-hover:scale-110 transition">
                        <flux:icon.beaker />
                    </div>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">{{ __('Horario de Prueba') }}</div>
                    <div class="text-xs text-zinc-500">{{ __('Simulacros y prácticas') }}</div>
                </button>

                <button wire:click="selectScheduleType('MAKEUP')"
                        class="p-6 rounded-xl border-2 border-zinc-200 dark:border-zinc-700 hover:border-purple-500 hover:bg-purple-50 dark:hover:bg-purple-900/10 transition text-left group">
                    <div class="w-10 h-10 rounded-lg bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center text-purple-600 mb-3 group-hover:scale-110 transition">
                        <flux:icon.arrow-path />
                    </div>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">{{ __('Horario de Recuperacion') }}</div>
                    <div class="text-xs text-zinc-500">{{ __('Sesiones de recuperacion') }}</div>
                </button>
            </div>
        </div>

    {{-- Step 2: Schedule Form --}}
    @else
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <flux:badge size="sm" color="{{ match($scheduleType) {
                        'OFFICIAL' => 'green',
                        'EVALUATION' => 'red',
                        'TEST' => 'yellow',
                        'MAKEUP' => 'purple',
                        default => 'gray',
                    } }}">
                        {{ match($scheduleType) {
                            'OFFICIAL' => __('Oficial'),
                            'EVALUATION' => __('Evaluacion'),
                            'TEST' => __('Prueba'),
                            'MAKEUP' => __('Recuperacion'),
                            default => $scheduleType,
                        } }}
                    </flux:badge>
                    <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ __('Tipo de horario seleccionado') }}
                    </span>
                </div>
                <flux:button wire:click="changeScheduleType" size="sm" variant="ghost">{{ __('Cambiar tipo') }}</flux:button>
            </div>
        </div>

        <form wire:submit.prevent="save" class="space-y-6 max-w-4xl">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion Academica') }}</flux:heading>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <flux:field>
                            <flux:label>{{ __('Jornada') }} *</flux:label>
                            <flux:select wire:model.live="selectedJornada" placeholder="{{ __('Seleccione jornada...') }}">
                                <flux:select.option value="">{{ __('Seleccione jornada...') }}</flux:select.option>
                                @foreach($allJornadas as $jornada)
                                    <flux:select.option value="{{ $jornada->id }}">{{ $jornada->shift_name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @error('selectedJornada') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Nivel') }} *</flux:label>
                            <flux:select wire:model.live="selectedNivel" placeholder="{{ __('Seleccione nivel...') }}">
                                <flux:select.option value="">{{ __('Seleccione nivel...') }}</flux:select.option>
                                @foreach($filteredNiveles as $nivel)
                                    <flux:select.option value="{{ $nivel->id }}">{{ $nivel->nivel_name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @error('selectedNivel') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Grado') }} *</flux:label>
                            <flux:select wire:model.live="selectedGrade" placeholder="{{ __('Seleccione grado...') }}">
                                <flux:select.option value="">{{ __('Seleccione grado...') }}</flux:select.option>
                                @foreach($filteredGrados as $grado)
                                    <flux:select.option value="{{ $grado->id }}">{{ $grado->grade_name }} - {{ $grado->section ?? '' }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @error('selectedGrade') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                        </flux:field>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>{{ __('Area') }} *</flux:label>
                            <flux:select wire:model.live="selectedArea" placeholder="{{ __('Seleccione area...') }}">
                                <flux:select.option value="">{{ __('Seleccione area...') }}</flux:select.option>
                                @foreach($allAreas as $area)
                                    <flux:select.option value="{{ $area->id }}">{{ $area->area_name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @error('selectedArea') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Asignatura') }} *</flux:label>
                            <flux:select wire:model.live="selectedSubject" placeholder="{{ __('Seleccione asignatura...') }}">
                                <flux:select.option value="">{{ __('Seleccione asignatura...') }}</flux:select.option>
                                @if($selectedArea)
                                    @foreach($allAreas->firstWhere('id', $selectedArea)?->subjects ?? [] as $subject)
                                        <flux:select.option value="{{ $subject->id }}">{{ $subject->subject_name }}</flux:select.option>
                                    @endforeach
                                @endif
                            </flux:select>
                            @error('selectedSubject') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                        </flux:field>
                    </div>
                    @if($this->requiresTrimester())
                        <flux:field>
                            <flux:label>{{ __('Trimestre') }} *</flux:label>
                            <flux:select wire:model="trimester_id" placeholder="{{ __('Seleccione trimestre...') }}">
                                <flux:select.option value="">{{ __('Seleccione trimestre...') }}</flux:select.option>
                                @foreach($trimestres as $trimestre)
                                    <flux:select.option value="{{ $trimestre->id }}">{{ $trimestre->trimester_name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion del Horario') }}</flux:heading>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>{{ __('Dia') }} *</flux:label>
                            <flux:select wire:model="day">
                                <flux:select.option value="LUNES">{{ __('Lunes') }}</flux:select.option>
                                <flux:select.option value="MARTES">{{ __('Martes') }}</flux:select.option>
                                <flux:select.option value="MIERCOLES">{{ __('Miércoles') }}</flux:select.option>
                                <flux:select.option value="JUEVES">{{ __('Jueves') }}</flux:select.option>
                                <flux:select.option value="VIERNES">{{ __('Viernes') }}</flux:select.option>
                            </flux:select>
                            @error('day') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Aula') }}</flux:label>
                            <flux:input wire:model="classroom" placeholder="{{ __('Ej: Aula 101') }}" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Hora de Inicio') }} *</flux:label>
                            <flux:input wire:model="start_time" type="time" />
                            @error('start_time') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Hora de Fin') }} *</flux:label>
                            <flux:input wire:model="end_time" type="time" />
                            @error('end_time') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                        </flux:field>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <flux:icon.plus /> {{ __('Guardar Hora') }}
                </flux:button>
                <flux:button wire:click="resetForm" variant="ghost">{{ __('Limpiar formulario') }}</flux:button>
            </div>
        </form>
    @endif

    {{-- Teacher's Existing Schedules Table --}}
    @php
        $schedules = $this->getTeacherSchedules();
    @endphp

    @if($schedules->count() > 0)
        <div class="mt-8 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                <div class="flex items-center justify-between">
                    <flux:heading size="md">{{ __('Horas Registradas') }}</flux:heading>
                    <flux:badge size="sm">{{ $schedules->count() }} {{ __('horas') }}</flux:badge>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30">
                            <th class="px-4 py-3 text-left font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Dia') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Horario') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Jornada') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Nivel') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Grado') }}</th>
                            <th class="px-4 py-3 text-left font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Asignatura') }}</th>
                            <th class="px-4 py-3 text-center font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Est.') }}</th>
                            <th class="px-4 py-3 text-center font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($schedules as $schedule)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition">
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" color="blue">
                                        {{ match($schedule->day) {
                                            'LUNES' => __('Lun'),
                                            'MARTES' => __('Mar'),
                                            'MIERCOLES' => __('Mié'),
                                            'JUEVES' => __('Jue'),
                                            'VIERNES' => __('Vie'),
                                            'SABADO' => __('Sab'),
                                            default => $schedule->day,
                                        } }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $schedule->start_time?->format('H:i') }} - {{ $schedule->end_time?->format('H:i') }}
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $schedule->grade->nivel->shift->shift_name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $schedule->grade->nivel->nivel_name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $schedule->grade->grade_name ?? '-' }}{{ $schedule->grade->section ? ' - ' . $schedule->grade->section : '' }}
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $schedule->subject->subject_name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <flux:badge size="sm" color="green">
                                        {{ $this->getStudentCountForGrade($schedule->grade_id) }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        <flux:button wire:click="openAttendanceModal({{ $schedule->id }})" size="sm" variant="ghost" title="{{ __('Tomar asistencia') }}">
                                            <flux:icon.check-circle />
                                        </flux:button>
                                        <flux:button wire:click="openTaskModal({{ $schedule->id }})" size="sm" variant="ghost" title="{{ __('Registrar tarea') }}">
                                            <flux:icon.clipboard-document-list />
                                        </flux:button>
                                        <a href="{{ route('admin.teacher.schedule.edit', $schedule->id) }}" wire:navigate
                                           class="inline-flex items-center justify-center p-1.5 rounded-md text-zinc-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition" title="{{ __('Editar') }}">
                                            <flux:icon.pencil />
                                        </a>
                                        <flux:button wire:click="deleteSchedule({{ $schedule->id }})" wire:confirm="{{ __('Eliminar este horario?') }}" size="sm" variant="ghost" title="{{ __('Eliminar') }}">
                                            <flux:icon.trash />
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="mt-8 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center">
            <flux:icon.calendar class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <p class="text-zinc-500 dark:text-zinc-400 font-medium">{{ __('No tienes horas registradas aun') }}</p>
            <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-1">{{ __('Crea tu primer horario usando el formulario de arriba') }}</p>
        </div>
    @endif

    {{-- Attendance Modal --}}
    @if($this->showAttendanceModal)
        <flux:modal wire:model.live="showAttendanceModal" :title="__('Tomar Asistencia')" class="max-w-3xl">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:label>{{ __('Fecha') }}</flux:label>
                        <flux:input type="date" wire:model="attendanceDate" />
                    </div>
                </div>

                @if(count($attendanceStudents) > 0)
                    <div class="border rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-zinc-50 dark:bg-zinc-800/50 border-b">
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-zinc-500">{{ __('Estudiante') }}</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-zinc-500 w-24">{{ __('Estado') }}</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-zinc-500 w-20">{{ __('Hora') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-zinc-500">{{ __('Observacion') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach($attendanceStudents as $enrollment)
                                    @php $sid = $enrollment['student_id']; @endphp
                                    <tr>
                                        <td class="px-3 py-2 font-medium">
                                            {{ $enrollment['student']['user']['lastname'] ?? '' }} {{ $enrollment['student']['user']['name'] ?? '' }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <flux:select wire:model="attendanceStatuses.{{ $sid }}" class="text-xs">
                                                <flux:select.option value="P">{{ __('Presente') }}</flux:select.option>
                                                <flux:select.option value="I">{{ __('F. Injustificada') }}</flux:select.option>
                                                <flux:select.option value="A">{{ __('Atraso') }}</flux:select.option>
                                                <flux:select.option value="J">{{ __('F. Justificada') }}</flux:select.option>
                                                <flux:select.option value="AI">{{ __('Ab. Institucional') }}</flux:select.option>
                                                <flux:select.option value="AA">{{ __('Ab. Aula') }}</flux:select.option>
                                            </flux:select>
                                        </td>
                                        <td class="px-3 py-2">
                                            <flux:input type="time" wire:model="attendanceTimes.{{ $sid }}" class="text-xs" />
                                        </td>
                                        <td class="px-3 py-2">
                                            <flux:input wire:model="attendanceObservations.{{ $sid }}" placeholder="{{ __('...') }}" class="text-xs" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 text-zinc-400">
                        <p>{{ __('No hay estudiantes matriculados en este grado.') }}</p>
                    </div>
                @endif
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="$set('showAttendanceModal', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="saveAttendance" variant="primary">{{ __('Guardar Asistencia') }}</flux:button>
            </div>
        </flux:modal>
    @endif

    {{-- Task/Observation Modal --}}
    @if($showTaskModal)
        <flux:modal wire:model.live="showTaskModal" :title="__('Registrar Tarea / Observacion')" class="max-w-lg">
            <div class="space-y-4">
                <div>
                    <flux:label>{{ __('Fecha') }}</flux:label>
                    <flux:input type="date" wire:model="taskDate" />
                </div>
                <div>
                    <flux:label>{{ __('Tema de la clase') }}</flux:label>
                    <flux:input wire:model="taskTopic" placeholder="{{ __('Ej: Fracciones decimales') }}" />
                </div>
                <div>
                    <flux:label>{{ __('Observaciones / Tareas') }}</flux:label>
                    <flux:textarea wire:model="taskObservation" rows="4" placeholder="{{ __('Describe las tareas asignadas, observaciones del dia, etc.') }}" />
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="$set('showTaskModal', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                    <flux:button wire:click="saveTask" variant="primary">{{ __('Guardar') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
