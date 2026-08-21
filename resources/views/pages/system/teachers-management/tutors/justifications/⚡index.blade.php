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

new #[Title('Justificaciones')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 15;
    public string $gradeName = '';
    public string $shiftName = '';
    public bool $found = false;

    public function mount(): void
    {
        $this->findTutorGrade();
    }

    protected function findTutorGrade(): void
    {
        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade.nivel.shift')
            ->first();

        if (!$tutorSchedule) {
            $this->found = false;
            return;
        }

        $this->gradeName = trim(($tutorSchedule->grade->grade_name ?? '') . ' ' . ($tutorSchedule->grade->section ?? ''));
        $this->shiftName = $tutorSchedule->grade->nivel->shift->shift_name ?? '';
        $this->found = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getRecordsProperty()
    {
        if (!$this->found) {
            return collect();
        }

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->first();

        if (!$tutorSchedule) {
            return collect();
        }

        $gradeId = $tutorSchedule->grade_id;

        $studentIds = StudentEnrollment::where('year_id', $yearId)
            ->where('grade_id', $gradeId)
            ->where('status', 'active')
            ->pluck('student_id');

        $attendances = Attendance::whereIn('student_id', $studentIds)
            ->where('year_id', $yearId)
            ->whereIn('status', ['I', 'J'])
            ->get()
            ->groupBy('student_id');

        $query = Student::query()
            ->with(['user'])
            ->whereIn('students.id', $studentIds);

        return $query
            ->when($this->search, fn ($q) =>
                $q->where('student_code', 'like', "%{$this->search}%")
                    ->orWhereHas('user', fn ($u) =>
                        $u->where('name', 'like', "%{$this->search}%")
                            ->orWhere('lastname', 'like', "%{$this->search}%")
                            ->orWhere('dni', 'like', "%{$this->search}%")
                    )
            )
            ->orderByRaw("COALESCE(NULLIF((SELECT u.lastname FROM users u WHERE u.id = students.user_id), ''), 'zzz')")
            ->paginate($this->perPage)
            ->through(function ($student) use ($attendances) {
                $studentAttendances = $attendances->get($student->id, collect());
                return (object) [
                    'id' => $student->id,
                    'student_code' => $student->student_code,
                    'fullname' => $student->user?->fullname ?? '-',
                    'unjustified_count' => $studentAttendances->where('status', 'I')->count(),
                    'justified_count' => $studentAttendances->where('status', 'J')->count(),
                    'has_unjustified' => $studentAttendances->where('status', 'I')->count() > 0,
                ];
            });
    }

    public function getStudentCount(): int
    {
        if (!$this->found) return 0;

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->first();

        if (!$tutorSchedule) return 0;

        return StudentEnrollment::where('year_id', $yearId)
            ->where('grade_id', $tutorSchedule->grade_id)
            ->where('status', 'active')
            ->count();
    }

    public function getTotalUnjustified(): int
    {
        if (!$this->found) return 0;

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->first();

        if (!$tutorSchedule) return 0;

        $studentIds = StudentEnrollment::where('year_id', $yearId)
            ->where('grade_id', $tutorSchedule->grade_id)
            ->where('status', 'active')
            ->pluck('student_id');

        return Attendance::whereIn('student_id', $studentIds)
            ->where('year_id', $yearId)
            ->where('status', 'I')
            ->count();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Justificaciones') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Gestion de justificaciones de inasistencias del grado tutorado') }}</flux:text>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Justificaciones') }}</span>
    </nav>

    @if($found)
        {{-- Grade Info --}}
        <div class="flex items-center gap-4 mb-6 px-5 py-4 bg-fuchsia-50 dark:bg-fuchsia-900/20 border border-fuchsia-200 dark:border-fuchsia-800 rounded-xl">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-fuchsia-100 dark:bg-fuchsia-900/40">
                    <flux:icon.document-text class="size-5 text-fuchsia-600 dark:text-fuchsia-400" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $gradeName }}</h2>
                    <div class="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                        @if($shiftName)
                            <span>{{ $shiftName }}</span>
                        @endif
                        <span>{{ $this->getStudentCount() }} {{ __('estudiantes') }}</span>
                        <span class="text-red-600 font-semibold">{{ $this->getTotalUnjustified() }} {{ __('inasistencias sin justificar') }}</span>
                    </div>
                </div>
            </div>
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
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estudiante') }}</th>
                            <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('F. Injustificadas') }}</th>
                            <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('F. Justificadas') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($this->records as $student)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                <td class="px-4 py-3"><flux:badge color="blue">{{ $student->student_code }}</flux:badge></td>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $student->fullname }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $student->unjustified_count > 0 ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-zinc-50 text-zinc-400 dark:bg-zinc-800' }}">
                                        {{ $student->unjustified_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $student->justified_count > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-zinc-50 text-zinc-400 dark:bg-zinc-800' }}">
                                        {{ $student->justified_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <flux:button href="{{ route('admin.teacher.tutor-justifications.show', $student->id) }}" wire:navigate size="sm" variant="ghost" icon="document-text">
                                        {{ __('Justificar') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-16 text-center">
                                    <flux:icon.document-text class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                    <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron estudiantes.') }}</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $this->records->links() }}</div>
        </div>
    @else
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.document-text class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('No se encontró asignación de tutoría') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('No se encontró una asignatura de Acompañamiento integral en el aula asociada a su usuario.') }}</p>
        </div>
    @endif
</div>

