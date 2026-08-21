<?php

declare(strict_types=1);

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reportes de Notas')] class extends Component {
    public string $search = '';
    public ?int $selectedTrimesterId = null;
    public bool $found = false;
    public string $gradeName = '';
    public string $shiftName = '';
    public int $gradeId = 0;
    public int $yearId = 0;

    public function mount(): void
    {
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        $this->findTutorGrade();
    }

    protected function findTutorGrade(): void
    {
        $teacherId = auth()->user()->teacher?->id;

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $this->yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade.nivel.shift')
            ->first();

        if (!$tutorSchedule) {
            $this->found = false;
            return;
        }

        $this->gradeId = $tutorSchedule->grade_id;
        $this->gradeName = trim(($tutorSchedule->grade->grade_name ?? '') . ' / ' . ($tutorSchedule->grade->section ?? ''));
        $this->shiftName = $tutorSchedule->grade->nivel->shift->shift_name ?? '';
        $this->found = true;
    }

    public function getTrimestersProperty()
    {
        return AcademicPeriod::where('year_id', $this->yearId)
            ->where('status', 1)
            ->where('is_supletorio', false)
            ->orderBy('id')
            ->get();
    }

    public function getStudentsProperty()
    {
        if (!$this->found) return collect();

        $studentIds = StudentEnrollment::where('year_id', $this->yearId)
            ->where('grade_id', $this->gradeId)
            ->where('status', 'active')
            ->pluck('student_id');

        return Student::whereIn('id', $studentIds)
            ->with('user')
            ->when($this->search, fn ($q) =>
                $q->where('student_code', 'like', "%{$this->search}%")
                    ->orWhereHas('user', fn ($u) =>
                        $u->where('name', 'like', "%{$this->search}%")
                            ->orWhere('lastname', 'like', "%{$this->search}%")
                            ->orWhere('dni', 'like', "%{$this->search}%")
                    )
            )
            ->orderByRaw("COALESCE(NULLIF((SELECT u.lastname FROM users u WHERE u.id = students.user_id), ''), 'zzz')")
            ->get();
    }

    public function getPrintAnnualUrl(int $studentId): string
    {
        return route('admin.teacher.tutor-grade-reports.pdf.student-report', [
            'student_id' => $studentId,
        ]);
    }

    public function getPrintTrimesterUrl(int $studentId): string
    {
        return route('admin.teacher.tutor-grade-reports.pdf.student-report-trimester', [
            'student_id'   => $studentId,
            'trimester_id' => $this->selectedTrimesterId,
        ]);
    }

    public function getPrintFormativeTrimesterUrl(int $studentId): string
    {
        return route('admin.teacher.tutor-grade-reports.pdf.student-formative-trimester', [
            'student_id'   => $studentId,
            'trimester_id' => $this->selectedTrimesterId,
        ]);
    }

    public function getAllStudentsTrimesterUrl(): string
    {
        return route('admin.teacher.tutor-grade-reports.pdf.all-students-trimester', [
            'trimester_id' => $this->selectedTrimesterId,
        ]);
    }

    public function getPrintComprehensiveAnnualUrl(int $studentId): string
    {
        return route('admin.teacher.tutor-grade-reports.pdf.student-annual-report', [
            'student_id' => $studentId,
        ]);
    }

    public function getPrintTrimesterReportUrl(int $studentId): string
    {
        return route('admin.teacher.tutor-grade-reports.pdf.student-trimester-report', [
            'student_id' => $studentId,
            'trimester_id' => $this->selectedTrimesterId,
        ]);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Reportes de Notas') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Buscar un estudiante e imprimir su reporte de calificaciones') }}</flux:text>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Reportes de Notas') }}</span>
    </nav>

    @if($this->found)
        {{-- Grade Info --}}
        <div class="flex items-center gap-4 mb-6 px-5 py-4 bg-fuchsia-50 dark:bg-fuchsia-900/20 border border-fuchsia-200 dark:border-fuchsia-800 rounded-xl">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-fuchsia-100 dark:bg-fuchsia-900/40">
                    <flux:icon.academic-cap class="size-5 text-fuchsia-600 dark:text-fuchsia-400" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->gradeName }}</h2>
                    <div class="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                        @if($this->shiftName)
                            <span>{{ $this->shiftName }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Trimester Selector --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-6">
            <div class="mb-4">
                <flux:heading size="sm" class="mb-1">{{ __('Seleccionar Trimestre') }}</flux:heading>
                <flux:text variant="subtle" class="text-xs">{{ __('Seleccione un trimestre para imprimir el reporte por trimestre.') }}</flux:text>
            </div>
            <div class="w-full sm:w-96">
                <flux:select wire:model.live="selectedTrimesterId">
                    <option value="">-- {{ __('Seleccionar trimestre') }} --</option>
                    @foreach($this->trimesters as $trimester)
                        <option value="{{ $trimester->id }}">{{ $trimester->trimester_name }}</option>
                    @endforeach
                </flux:select>
            </div>
            @if($this->selectedTrimesterId)
                <div class="mt-4">
                    <flux:button
                        href="{{ $this->getAllStudentsTrimesterUrl() }}"
                        target="_blank"
                        variant="primary"
                        icon="printer">
                        {{ __('Imprimir Reporte de Todos los Estudiantes') }}
                    </flux:button>
                </div>
            @endif
        </div>

        {{-- Search --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-6">
            <div class="w-full sm:w-96">
                <flux:input wire:model.live.debounce="search" :placeholder="__('Buscar por codigo, nombre, apellido o DNI...')" icon="magnifying-glass" />
            </div>
        </div>

        {{-- Table --}}
        <div>
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Codigo') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Nombre') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('DNI') }}</th>
                            <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($this->students as $student)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                <td class="px-4 py-3"><flux:badge color="blue">{{ $student->student_code }}</flux:badge></td>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $student->user?->fullname ?? '-' }}</td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $student->user?->dni }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        {{-- <flux:button
                                            href="{{ $this->getPrintAnnualUrl($student->id) }}"
                                            target="_blank"
                                            size="sm"
                                            variant="primary"
                                            icon="printer">
                                            {{ __('Anual') }}
                                        </flux:button> --}}
                                        <flux:button
                                            href="{{ $this->getPrintComprehensiveAnnualUrl($student->id) }}"
                                            target="_blank"
                                            size="sm"
                                            variant="filled"
                                            color="violet"
                                            icon="document-check">
                                            {{ __('Reporte Anual') }}
                                        </flux:button>
                                        @if($this->selectedTrimesterId)
                                            <flux:button
                                                href="{{ $this->getPrintTrimesterReportUrl($student->id) }}"
                                                target="_blank"
                                                size="sm"
                                                variant="filled"
                                                color="rose"
                                                icon="clipboard-document-check">
                                                {{ __('Trimestre') }}
                                            </flux:button>
                                        @endif
                                        @if($this->selectedTrimesterId)
                                            <flux:button
                                                href="{{ $this->getPrintFormativeTrimesterUrl($student->id) }}"
                                                target="_blank"
                                                size="sm"
                                                variant="filled"
                                                color="blue"
                                                icon="document-chart-bar">
                                                {{ __('Formativas') }}
                                            </flux:button>
                                            {{-- <flux:button
                                                href="{{ $this->getPrintTrimesterUrl($student->id) }}"
                                                target="_blank"
                                                size="sm"
                                                variant="filled"
                                                color="emerald"
                                                icon="printer">
                                                {{ __('Trimestre') }}
                                            </flux:button> --}}
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-16 text-center">
                                    <flux:icon.academic-cap class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                    <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron estudiantes.') }}</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.academic-cap class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('No se encontró asignación de tutoría') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('No se encontró una asignatura de Acompañamiento integral en el aula asociada a su usuario.') }}</p>
        </div>
    @endif
</div>
