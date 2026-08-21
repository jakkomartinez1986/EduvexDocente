<?php

declare(strict_types=1);

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Services\AcademicYearService;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Libro de Asistencias')] class extends Component {
    use WithPagination;

    public ?int $yearId = null;
    public ?int $selectedGradeId = null;
    public ?int $selectedSubjectId = null;
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $search = '';
    public int $perPage = 25;

    public array $allSchedules = [];
    public array $grades = [];
    public array $subjects = [];
    public array $attendanceDates = [];
    public array $studentAttendance = [];

    public function mount(): void
    {
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        $now = now();
        $this->dateFrom = $now->copy()->startOfMonth()->format('Y-m-d');
        $this->dateTo = $now->format('Y-m-d');
        $this->loadTeacherData();
    }

    protected function loadTeacherData(): void
    {
        $teacherId = auth()->user()->teacher?->id;

        $this->allSchedules = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $this->yearId)
            ->with('grade.nivel.shift', 'subject')
            ->get()
            ->toArray();

        $this->grades = collect($this->allSchedules)
            ->pluck('grade')
            ->unique('id')
            ->values()
            ->all();

        if (count($this->grades) === 1 && !$this->selectedGradeId) {
            $this->selectedGradeId = $this->grades[0]['id'];
            $this->loadSubjectsForGrade();
        }
    }

    public function updatedSelectedGradeId(): void
    {
        $this->selectedSubjectId = null;
        $this->loadSubjectsForGrade();

        if (count($this->subjects) === 1) {
            $this->selectedSubjectId = $this->subjects[0]['id'];
        }

        $this->loadBook();
    }

    public function updatedSelectedSubjectId(): void
    {
        $this->loadBook();
    }

    public function updatedDateFrom(): void
    {
        $this->loadBook();
    }

    public function updatedDateTo(): void
    {
        $this->loadBook();
    }

    protected function loadSubjectsForGrade(): void
    {
        $this->subjects = collect($this->allSchedules)
            ->where('grade_id', $this->selectedGradeId)
            ->pluck('subject')
            ->unique('id')
            ->values()
            ->all();
    }

    public function loadBook(): void
    {
        if (!$this->selectedGradeId || !$this->selectedSubjectId) {
            $this->attendanceDates = [];
            $this->studentAttendance = [];
            return;
        }

        $scheduleIds = collect($this->allSchedules)
            ->filter(fn ($s) => $s['grade_id'] == $this->selectedGradeId && $s['subject_id'] == $this->selectedSubjectId)
            ->pluck('id')
            ->toArray();

        $from = $this->dateFrom ?: now()->startOfMonth()->format('Y-m-d');
        $to = $this->dateTo ?: now()->format('Y-m-d');

        $attendances = Attendance::where('year_id', $this->yearId)
            ->whereIn('class_schedule_id', $scheduleIds)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->whereNotNull('status')
            ->get()
            ->groupBy('date');

        $this->attendanceDates = $attendances->keys()->sort()->values()->toArray();

        $studentIds = StudentEnrollment::where('grade_id', $this->selectedGradeId)
            ->where('year_id', $this->yearId)
            ->pluck('student_id')
            ->toArray();

        $students = Student::whereIn('id', $studentIds)
            ->with('user')
            ->get();

        if ($this->search) {
            $students = $students->filter(function ($s) {
                $name = strtolower($s->user->full_name ?? trim(($s->user->lastname ?? '') . ' ' . ($s->user->name ?? '')));
                return str_contains($name, strtolower($this->search));
            });
        }

        $this->studentAttendance = $students->map(function ($student) use ($attendances) {
            $records = [];
            foreach ($this->attendanceDates as $date) {
                $dayAttendances = $attendances->get($date, collect());
                $record = $dayAttendances->firstWhere('student_id', $student->id);
                $records[$date] = $record ? [
                    'status' => trim($record->status),
                    'observation' => $record->observation,
                    'arrival_time' => $record->arrival_time?->format('H:i'),
                    'id' => $record->id,
                ] : null;
            }

            $totalRecords = collect($records)->filter()->count();
            $absences = collect($records)->filter(fn ($r) => in_array($r['status'] ?? '', ['I', 'AI', 'AA']))->count();

            return [
                'id' => $student->id,
                'name' => $student->user->full_name ?? trim(($student->user->lastname ?? '') . ' ' . ($student->user->name ?? '')),
                'code' => $student->student_code,
                'records' => $records,
                'total_records' => $totalRecords,
                'absences' => $absences,
            ];
        })->toArray();
    }

    public function getSubjectName(): string
    {
        $schedule = collect($this->allSchedules)->firstWhere('subject_id', $this->selectedSubjectId);
        return $schedule['subject']['subject_name'] ?? '';
    }

    public function getGradeName(): string
    {
        $schedule = collect($this->allSchedules)->firstWhere('grade_id', $this->selectedGradeId);
        if (!$schedule || !$schedule['grade']) return '';
        return ($schedule['grade']['grade_name'] ?? '') . ' ' . ($schedule['grade']['section'] ?? '');
    }

    public function getShiftName(): string
    {
        $schedule = collect($this->allSchedules)->firstWhere('grade_id', $this->selectedGradeId);
        return $schedule['grade']['nivel']['shift']['shift_name'] ?? '';
    }

    public function getStatusLabel(string $code): string
    {
        return match($code) {
            'P' => 'Presente',
            'A' => 'Atraso',
            'I' => 'F. Injustificada',
            'J' => 'F. Justificada',
            'AI' => 'Ab. Institucional',
            'AA' => 'Ab. Aula',
            default => $code,
        };
    }

    public function getStatusColor(string $code): string
    {
        return match($code) {
            'P' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'A' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'I' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'J' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
            'AI' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
            'AA' => 'bg-red-200 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            default => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
        };
    }

    public function getDaySummary(string $date): array
    {
        $records = collect($this->studentAttendance)
            ->map(fn ($s) => $s['records'][$date] ?? null)
            ->filter();

        $totalStudents = count($this->studentAttendance);

        return [
            'total' => $totalStudents,
            'recorded' => $records->count(),
            'absent' => $records->filter(fn ($r) => in_array($r['status'] ?? '', ['I', 'AI', 'AA']))->count(),
            'late' => $records->filter(fn ($r) => $r['status'] === 'A')->count(),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Libro de Asistencias') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Vista general de asistencias por asignatura y grado') }}</flux:text>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Libro de Asistencias') }}</span>
    </nav>

    {{-- Selectors --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div>
            <flux:label>{{ __('Grado') }}</flux:label>
            <flux:select wire:model.live="selectedGradeId" placeholder="{{ __('Seleccione grado') }}">
                @foreach($grades as $grade)
                    <flux:select.option value="{{ $grade['id'] }}">{{ $grade['grade_name'] }} {{ $grade['section'] ?? '' }} - {{ $grade['nivel']['shift']['shift_name'] ?? '' }}</flux:select.option>
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
            <flux:label>{{ __('Desde') }}</flux:label>
            <flux:input type="date" wire:model.live="dateFrom" />
        </div>
        <div>
            <flux:label>{{ __('Hasta') }}</flux:label>
            <flux:input type="date" wire:model.live="dateTo" />
        </div>
    </div>

    @if($selectedSubjectId && $selectedGradeId)
        {{-- Info Header --}}
        <div class="flex items-center gap-4 mb-4 px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl">
            <div class="flex-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->getSubjectName() }}</h2>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $this->getGradeName() }}</span>
                    <span class="text-sm text-zinc-500">{{ $this->getShiftName() }}</span>
                    <span class="text-sm text-zinc-500">{{ count($studentAttendance) }} {{ __('estudiantes') }}</span>
                </div>
            </div>
            @if(count($attendanceDates) > 0)
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">{{ __('Dias con registro:') }}</span>
                    <span class="px-2.5 py-0.5 bg-white dark:bg-zinc-800 rounded-full text-xs font-bold text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                        {{ count($attendanceDates) }}
                    </span>
                </div>
            @endif
        </div>

        {{-- Status Legend --}}
        <div class="flex flex-wrap gap-2 mb-4 px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 self-center mr-1">{{ __('Leyenda:') }}</span>
            @php
                $legend = [
                    'P' => ['Presente', 'bg-emerald-500'],
                    'A' => ['Atraso', 'bg-amber-500'],
                    'I' => ['F. Injustificada', 'bg-red-500'],
                    'J' => ['F. Justificada', 'bg-violet-500'],
                    'AI' => ['Ab. Institucional', 'bg-teal-500'],
                    'AA' => ['Ab. Aula', 'bg-red-700'],
                ];
            @endphp
            @foreach($legend as $code => [$label, $bg])
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:text-zinc-400">
                    <span class="w-2.5 h-2.5 rounded-sm {{ $bg }}"></span>
                    {{ $code }} = {{ $label }}
                </span>
            @endforeach
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:text-zinc-400">
                <span class="w-2.5 h-2.5 rounded-sm bg-zinc-200 dark:bg-zinc-600"></span>
                {{ __('Sin registro') }} = {{ __('Presente (calculado)') }}
            </span>
        </div>

        {{-- Search --}}
        <div class="mb-4 max-w-sm">
            <flux:input wire:model.live.debounce="search" :placeholder="__('Buscar estudiante...')" icon="magnifying-glass" />
        </div>

        @if(count($studentAttendance) > 0 && count($attendanceDates) > 0)
            {{-- Attendance Grid --}}
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 sticky left-0 bg-zinc-50 dark:bg-zinc-800/50 z-10 border-r border-zinc-200 dark:border-zinc-700 min-w-[180px]">
                                {{ __('Estudiante') }}
                            </th>
                            @foreach($attendanceDates as $date)
                                @php $summary = $this->getDaySummary($date); @endphp
                                <th class="px-2 py-3 text-center min-w-[60px]">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span class="text-[11px] font-bold text-zinc-900 dark:text-zinc-100">{{ \Carbon\Carbon::parse($date)->format('d') }}</span>
                                        <span class="text-[9px] font-medium text-zinc-400">{{ \Carbon\Carbon::parse($date)->format('M') }}</span>
                                        @if($summary['absent'] > 0)
                                            <span class="text-[9px] font-bold text-red-500">{{ $summary['absent'] }}F</span>
                                        @endif
                                    </div>
                                </th>
                            @endforeach
                            <th class="px-3 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400 min-w-[80px]">
                                <span class="text-[11px] font-bold">{{ __('Inasist.') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($studentAttendance as $student)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100 text-xs sticky left-0 bg-white dark:bg-zinc-900 z-10 border-r border-zinc-200 dark:border-zinc-700">
                                    {{ $student['name'] }}
                                    <span class="block text-[9px] font-mono text-zinc-400">{{ $student['code'] }}</span>
                                </td>
                                @foreach($attendanceDates as $date)
                                    @php $record = $student['records'][$date] ?? null; @endphp
                                    <td class="px-1 py-1 text-center">
                                        @if($record)
                                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded text-[10px] font-bold font-mono {{ $this->getStatusColor($record['status']) }}"
                                                  title="{{ $this->getStatusLabel($record['status']) }}{{ $record['observation'] ? ' — ' . $record['observation'] : '' }}">
                                                {{ $record['status'] }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center justify-center w-6 h-5 rounded text-[10px] font-mono text-zinc-300 dark:text-zinc-600 bg-zinc-50 dark:bg-zinc-800" title="{{ __('Presente (sin registro)') }}">
                                                P
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold font-mono
                                        {{ $student['absences'] > 0 ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' }}">
                                        {{ $student['absences'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($attendanceDates) + 2 }}" class="px-4 py-16 text-center">
                                    <flux:icon.archive-box class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                                    <flux:text variant="subtle" class="text-sm">{{ __('No hay estudiantes matriculados.') }}</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Day Summary Bar --}}
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                @foreach($attendanceDates as $date)
                    @php $summary = $this->getDaySummary($date); @endphp
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-2.5 text-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">{{ \Carbon\Carbon::parse($date)->format('d/m') }}</span>
                        <div class="flex items-center justify-center gap-2 mt-1">
                            <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">{{ $summary['recorded'] }}/{{ $summary['total'] }}</span>
                            @if($summary['absent'] > 0)
                                <span class="text-[10px] font-bold text-red-500">{{ $summary['absent'] }}F</span>
                            @endif
                            @if($summary['late'] > 0)
                                <span class="text-[10px] font-bold text-amber-500">{{ $summary['late'] }}A</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif(count($attendanceDates) > 0)
            <div class="text-center py-12 text-zinc-400">
                <flux:icon.users class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                <p class="text-sm font-semibold">{{ __('No hay estudiantes matriculados en este grado.') }}</p>
            </div>
        @else
            <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700 border-dashed">
                <flux:icon.calendar-days class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
                <p class="text-base font-semibold">{{ __('Sin registros de asistencia') }}</p>
                <p class="text-sm text-zinc-400 mt-1">{{ __('No hay asistencias registradas en el rango de fechas seleccionado.') }}</p>
                <p class="text-xs text-zinc-400 mt-1">{{ __('Intente cambiar las fechas o seleccione otra asignatura/grado.') }}</p>
            </div>
        @endif
    @else
        {{-- No Subject/Grade Selected --}}
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.users class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('Seleccione un grado y una asignatura para comenzar') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('Use los selectores superiores para filtrar') }}</p>
        </div>
    @endif
</div>
