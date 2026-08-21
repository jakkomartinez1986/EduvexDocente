<?php

declare(strict_types=1);

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Services\AcademicYearService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalle de Asistencia')] class extends Component {
    public int $id;
    public ?array $schedule = null;
    public string $date = '';
    public ?array $observation = null;
    public array $students = [];

    public function mount(int $id): void
    {
        $this->date = now()->format('Y-m-d');

        $this->schedule = ClassSchedule::with('grade.nivel.shift', 'subject', 'teacher.user')
            ->find($id)?->toArray();

        if (!$this->schedule) {
            abort(404);
        }
    }

    public function updatedDate(): void
    {
        $this->loadDetail();
    }

    public function loadDetail(): void
    {
        if (!$this->schedule) return;

        $this->observation = ClassObservation::where('class_schedule_id', $this->schedule['id'])
            ->where('observation_date', $this->date)
            ->first()
            ?->toArray();

        $studentIds = StudentEnrollment::where('grade_id', $this->schedule['grade_id'])
            ->where('year_id', $this->schedule['year_id'])
            ->pluck('student_id')
            ->toArray();

        $attendances = Attendance::where('class_schedule_id', $this->schedule['id'])
            ->where('date', $this->date)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $students = Student::whereIn('id', $studentIds)
            ->with('user')
            ->get();

        $this->students = $students->map(function ($student) use ($attendances) {
            $att = $attendances->get($student->id);
            return [
                'id' => $student->id,
                'name' => $student->user->full_name ?? trim(($student->user->lastname ?? '') . ' ' . ($student->user->name ?? '')),
                'code' => $student->student_code,
                'status' => trim($att?->status ?? ''),
                'observation' => $att?->observation,
                'arrival_time' => $att?->arrival_time?->format('H:i'),
                'has_record' => $att !== null,
            ];
        })->toArray();
    }

    public function getSubjectName(): string
    {
        return $this->schedule['subject']['subject_name'] ?? '';
    }

    public function getGradeName(): string
    {
        return ($this->schedule['grade']['grade_name'] ?? '') . ' ' . ($this->schedule['grade']['section'] ?? '');
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
            'P' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'A' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'I' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'J' => 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
            'AI' => 'bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
            'AA' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            default => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
        };
    }

    public function getSummary(): array
    {
        $total = count($this->students);
        $recorded = collect($this->students)->filter(fn ($s) => $s['has_record'])->count();
        $absent = collect($this->students)->filter(fn ($s) => in_array($s['status'] ?? '', ['I', 'AI', 'AA']))->count();
        $late = collect($this->students)->filter(fn ($s) => $s['status'] === 'A')->count();
        $present = $total - $absent - $late;

        return compact('total', 'recorded', 'absent', 'late', 'present');
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Detalle de Asistencia') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ $this->getSubjectName() }} — {{ $this->getGradeName() }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('admin.teacher.attendance-book.index') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver al Libro') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.teacher.attendance-book.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Libro de Asistencias') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Detalle') }}</span>
    </nav>

    {{-- Date Selector --}}
    <div class="mb-6 max-w-xs">
        <flux:label>{{ __('Fecha') }}</flux:label>
        <flux:input type="date" wire:model.live="date" />
    </div>

    @if($schedule)
        {{-- Schedule Info --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">{{ __('Materia') }}</span>
                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->getSubjectName() }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">{{ __('Grado') }}</span>
                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->getGradeName() }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">{{ __('Horario') }}</span>
                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ \Carbon\Carbon::parse($schedule['start_time'])->format('H:i') }} — {{ \Carbon\Carbon::parse($schedule['end_time'])->format('H:i') }}
                </p>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">{{ __('Docente') }}</span>
                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $schedule['teacher']['user']['full_name'] ?? '—' }}</p>
            </div>
        </div>

        {{-- Summary Stats --}}
        @php $summary = $this->getSummary(); @endphp
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-3 text-center">
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Total') }}</span>
                <span class="text-xl font-extrabold text-zinc-900 dark:text-zinc-100 font-mono">{{ $summary['total'] }}</span>
            </div>
            <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/10 p-3 text-center">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block">{{ __('Presentes') }}</span>
                <span class="text-xl font-extrabold text-emerald-700 dark:text-emerald-400 font-mono">{{ $summary['present'] }}</span>
            </div>
            <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/10 p-3 text-center">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 block">{{ __('Atrasos') }}</span>
                <span class="text-xl font-extrabold text-amber-700 dark:text-amber-400 font-mono">{{ $summary['late'] }}</span>
            </div>
            <div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/10 p-3 text-center">
                <span class="text-[10px] font-bold uppercase tracking-wider text-red-600 dark:text-red-400 block">{{ __('Inasistencias') }}</span>
                <span class="text-xl font-extrabold text-red-700 dark:text-red-400 font-mono">{{ $summary['absent'] }}</span>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-3 text-center">
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Asistencia') }}</span>
                <span class="text-xl font-extrabold font-mono {{ $summary['total'] > 0 && ($summary['present'] / $summary['total']) >= 0.8 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                    {{ $summary['total'] > 0 ? number_format(($summary['present'] / $summary['total']) * 100, 0) : 0 }}%
                </span>
            </div>
        </div>

        {{-- Observation --}}
        @if($observation)
            <div class="mb-6 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">{{ __('Observacion de Clase') }}</span>
                <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ $observation['classtopic'] ?: '—' }}</p>
                @if($observation['observation'] ?? null)
                    <p class="text-xs text-zinc-500 mt-1">{{ $observation['observation'] }}</p>
                @endif
            </div>
        @endif

        {{-- Student List --}}
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">#</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estudiante') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estado') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Hora Llegada') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Observacion') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($students as $idx => $student)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                            <td class="px-4 py-2.5 text-zinc-400 text-xs">{{ $idx + 1 }}</td>
                            <td class="px-4 py-2.5 font-medium text-zinc-900 dark:text-zinc-100 text-xs">
                                {{ $student['name'] }}
                                <span class="block text-[9px] font-mono text-zinc-400">{{ $student['code'] }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                @if($student['has_record'])
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $this->getStatusColor($student['status']) }}">
                                        {{ $this->getStatusLabel($student['status']) }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        Presente
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-center text-xs text-zinc-500">
                                {{ $student['arrival_time'] ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-xs text-zinc-500 dark:text-zinc-400 max-w-[200px] truncate">
                                {{ $student['observation'] ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <flux:icon.archive-box class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                                <flux:text variant="subtle" class="text-sm">{{ __('No hay estudiantes matriculados.') }}</flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.calendar-days class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('Horario no encontrado') }}</p>
        </div>
    @endif
</div>

