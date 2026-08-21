<?php

use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Horario')] class extends Component
{
    public int $scheduleId;

    public ?int $yearId = null;

    public ?int $teacher_id = null;

    public ?string $scheduleType = null;

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

    public $allAreas = [];

    public $allJornadas = [];

    public $filteredNiveles = [];

    public $filteredGrados = [];

    public $trimestres = [];

    public function mount(int $id): void
    {
        $this->scheduleId = $id;
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        $this->allAreas = Area::with('subjects')->orderBy('area_name')->get();
        $this->allJornadas = Shift::where('status', 1)->get();
        $this->trimestres = AcademicPeriod::where('year_id', $this->yearId)->get();

        $schedule = ClassSchedule::with(['subject.area', 'grade.nivel.shift'])->findOrFail($id);

        $this->teacher_id = $schedule->teacher_id;
        $this->scheduleType = $schedule->schedule_type;

        $this->selectedSubject = $schedule->subject_id;
        $this->selectedArea = $schedule->subject?->area_id;

        $this->selectedGrade = $schedule->grade_id;
        $this->selectedNivel = $schedule->grade?->nivel_id;
        $this->selectedJornada = $schedule->grade?->nivel?->shift_id;

        if ($this->selectedJornada) {
            $this->filteredNiveles = Nivel::where('shift_id', $this->selectedJornada)->get();
        }
        if ($this->selectedNivel) {
            $this->filteredGrados = Grade::where('nivel_id', $this->selectedNivel)->get();
        }

        $this->day = $schedule->day;
        $this->start_time = $schedule->start_time?->format('H:i') ?? '';
        $this->end_time = $schedule->end_time?->format('H:i') ?? '';
        $this->classroom = $schedule->classroom;
        $this->trimester_id = $schedule->trimester_id;
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

    public function updatedSelectedArea(): void
    {
        $this->selectedSubject = null;
    }

    public function requiresTrimester(): bool
    {
        return in_array($this->scheduleType, ['EVALUATION', 'MAKEUP']);
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
            'day' => 'dia',
            'start_time' => 'hora de inicio',
            'end_time' => 'hora de fin',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $gradeActive = Grade::findOrFail($this->selectedGrade);
        $subject = Subject::findOrFail($this->selectedSubject);

        $isIntegralSupport = $subject->subject_name === 'Acompañamiento integral en el aula';

        if ($isIntegralSupport) {
            $existingQuery = ClassSchedule::where('teacher_id', $this->teacher_id)
                ->where('subject_id', $this->selectedSubject)
                ->where('year_id', $this->yearId)
                ->where('grade_id', '!=', $this->selectedGrade)
                ->where('id', '!=', $this->scheduleId);

            if ($existingQuery->exists()) {
                Flux::toast(variant: 'danger', text: __('Este docente ya tiene asignada la hora de Acompañamiento integral en otro curso. Solo se permite en un curso.'));

                return;
            }
        }

        $data = [
            'subject_id' => $this->selectedSubject,
            'grade_id' => $this->selectedGrade,
            'trimester_id' => $this->requiresTrimester() ? $this->trimester_id : null,
            'day' => $this->day,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'classroom' => $this->classroom ?? ($gradeActive->grade_name.' - '.($gradeActive->section ?? '')),
        ];

        ClassSchedule::findOrFail($this->scheduleId)->update($data);

        Flux::toast(variant: 'success', text: __('Horario actualizado correctamente.'));
        $this->redirect(route('admin.teacher.schedule.timeline'));
    }

    public function getScheduleTypeLabel(): string
    {
        return match ($this->scheduleType) {
            'OFFICIAL' => __('Oficial'),
            'EVALUATION' => __('Evaluacion'),
            'TEST' => __('Prueba'),
            'MAKEUP' => __('Recuperacion'),
            default => $this->scheduleType ?? '',
        };
    }

    public function getScheduleTypeColor(): string
    {
        return match ($this->scheduleType) {
            'OFFICIAL' => 'green',
            'EVALUATION' => 'red',
            'TEST' => 'yellow',
            'MAKEUP' => 'purple',
            default => 'gray',
        };
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Editar Horario') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Modificar datos del horario') }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('admin.teacher.schedule.timeline') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.teacher.schedule.timeline') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Horarios') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Editar') }}</span>
    </nav>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-6">
        <div class="flex items-center gap-3">
            <flux:badge size="sm" color="{{ $this->getScheduleTypeColor() }}">
                {{ $this->getScheduleTypeLabel() }}
            </flux:badge>
            <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                {{ __('Tipo de horario') }}
            </span>
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
                            <flux:select.option value="MIERCOLES">{{ __('Miercoles') }}</flux:select.option>
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
                <flux:icon.check /> {{ __('Actualizar Horario') }}
            </flux:button>
            <flux:button href="{{ route('admin.teacher.schedule.timeline') }}" wire:navigate variant="ghost">
                {{ __('Cancelar') }}
            </flux:button>
        </div>
    </form>
</div>
