<?php

declare(strict_types=1);

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\User;
use App\Services\AcademicYearService;
use App\Services\SchoolConfigService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Libro de Asistencias del Grado')] class extends Component {
    public string $gradeName = '';
    public string $shiftName = '';
    public bool $found = false;
    public string $filterMonth = '';
    public string $filterTrimester = '';
    public string $search = '';

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

    public function getSubjectsProperty(): array
    {
        if (!$this->found) return [];

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->first();

        if (!$tutorSchedule) return [];

        $schedules = ClassSchedule::where('grade_id', $tutorSchedule->grade_id)
            ->where('year_id', $yearId)
            ->with('subject', 'teacher.user')
            ->get();

        return $schedules->groupBy('subject_id')->map(fn ($group) => [
            'id' => $group->first()->id,
            'subject_id' => $group->first()->subject_id,
            'subject_name' => $group->first()->subject->subject_name ?? '—',
            'teacher_name' => $group->first()->teacher?->user?->fullname ?? '—',
            'schedule_ids' => $group->pluck('id')->toArray(),
        ])->values()->toArray();
    }

    public function getTrimestersProperty(): array
    {
        $yearId = app(AcademicYearService::class)->getActiveYearId();
        if (!$yearId) return [];

        return AcademicPeriod::where('year_id', $yearId)
            ->where('is_supletorio', false)
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'trimester_name', 'start_date', 'end_date'])
            ->toArray();
    }

    public function getSchoolProperty(): ?School
    {
        return app(SchoolConfigService::class)->getActiveSchool();
    }

    public function getAttendanceMatrixProperty(): array
    {
        if (!$this->found) return [];

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->first();

        if (!$tutorSchedule) return [];

        $gradeId = $tutorSchedule->grade_id;

        $scheduleIds = ClassSchedule::where('grade_id', $gradeId)
            ->where('year_id', $yearId)
            ->pluck('id');

        $studentIds = StudentEnrollment::where('year_id', $yearId)
            ->where('grade_id', $gradeId)
            ->where('status', 'active')
            ->pluck('student_id');

        $query = Attendance::whereIn('student_id', $studentIds)
            ->whereIn('class_schedule_id', $scheduleIds)
            ->whereIn('status', ['J', 'I'])
            ->where('year_id', $yearId);

        if ($this->filterTrimester) {
            $period = AcademicPeriod::find($this->filterTrimester);
            if ($period) {
                $query->whereBetween('date', [$period->start_date, $period->end_date]);
            }
        } elseif ($this->filterMonth) {
            $query->whereMonth('date', $this->filterMonth);
        }

        $attendances = $query->get();

        $students = Student::whereIn('id', $studentIds)
            ->with('user')
            ->get();

        if ($this->search) {
            $students = $students->filter(function ($s) {
                $name = mb_strtolower($s->user->fullname ?? trim(($s->user->lastname ?? '') . ' ' . ($s->user->name ?? '')));
                return str_contains($name, mb_strtolower($this->search));
            });
        }

        $subjects = $this->subjects;

        $matrix = [];
        $totalsBySubject = [];
        $globalTotalJ = 0;
        $globalTotalI = 0;

        foreach ($students as $student) {
            $row = [
                'name' => $student->user->fullname ?? trim(($student->user->lastname ?? '') . ' ' . ($student->user->name ?? '')),
                'code' => $student->student_code,
                'subjects' => [],
                'total_j' => 0,
                'total_i' => 0,
            ];

            foreach ($subjects as $subject) {
                $countJ = $attendances->where('student_id', $student->id)
                    ->whereIn('class_schedule_id', $subject['schedule_ids'])
                    ->where('status', 'J')
                    ->count();

                $countI = $attendances->where('student_id', $student->id)
                    ->whereIn('class_schedule_id', $subject['schedule_ids'])
                    ->where('status', 'I')
                    ->count();

                $row['subjects'][] = ['j' => $countJ, 'i' => $countI];
                $row['total_j'] += $countJ;
                $row['total_i'] += $countI;

                if (!isset($totalsBySubject[$subject['id']])) {
                    $totalsBySubject[$subject['id']] = ['j' => 0, 'i' => 0];
                }
                $totalsBySubject[$subject['id']]['j'] += $countJ;
                $totalsBySubject[$subject['id']]['i'] += $countI;

                $globalTotalJ += $countJ;
                $globalTotalI += $countI;
            }

            $matrix[] = $row;
        }

        return [
            'students' => $matrix,
            'totals_by_subject' => $totalsBySubject,
            'total_j' => $globalTotalJ,
            'total_i' => $globalTotalI,
            'student_count' => count($students),
        ];
    }

    public function getPeriodLabel(): string
    {
        if ($this->filterTrimester) {
            $period = collect($this->trimesters)->firstWhere('id', (int)$this->filterTrimester);
            return $period['trimester_name'] ?? '';
        }
        if ($this->filterMonth) {
            $months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            return $months[(int)$this->filterMonth] ?? '';
        }
        return 'Anual';
    }

    public function downloadPdf()
    {
        if (!$this->found) {
            return;
        }

        $matrix = $this->attendanceMatrix;
        $subjects = $this->subjects;
        $schoolData = $this->school;
        $yearId = app(AcademicYearService::class)->getActiveYearId();
        $yearName = ScolarYear::find($yearId)?->year_name ?? '';

        $teacherName = auth()->user()?->fullname ?? '';
        $inspector = User::role('INSPECTOR')->first();
        $inspectorName = $inspector?->fullname ?? '';

        $chartMax = max(
            collect($matrix['totals_by_subject'])->max('j') ?? 0,
            collect($matrix['totals_by_subject'])->max('i') ?? 0,
            10
        );

        $pdf = Pdf::loadView('pdf.attendance-book', [
            'school'            => $schoolData,
            'gradeName'         => $this->gradeName,
            'shiftName'         => $this->shiftName,
            'periodLabel'       => $this->getPeriodLabel(),
            'yearName'          => $yearName,
            'students'          => $matrix['students'],
            'subjects'          => $subjects,
            'totalsBySubject'   => $matrix['totals_by_subject'],
            'globalTotalJ'      => $matrix['total_j'],
            'globalTotalI'      => $matrix['total_i'],
            'studentCount'      => $matrix['student_count'],
            'chartMax'          => $chartMax,
            'teacherName'       => $teacherName,
            'inspectorName'     => $inspectorName,
        ]);

        $pdf->setPaper('a3', 'landscape');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');

        $filename = 'Libro_Asistencias_' . str_replace(['/', '\\', ':'], '-', $this->gradeName) . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Libro de Asistencias') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Reporte de asistencias del grado tutorado') }}</flux:text>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Libro de Asistencias') }}</span>
    </nav>

    @if($this->found)
        @php $matrix = $this->attendanceMatrix; @endphp
        @php $subjects = $this->subjects; @endphp
        @php $schoolData = $this->school; @endphp
        @php $trimesters = $this->trimesters; @endphp

        <div id="contenedorReporte">
            {{-- Institution Header --}}
            <div class="report-header mb-6 px-5 py-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl flex items-center justify-between print:border print:border-black print:rounded-none print:mb-4 print:px-4 print:py-3">
                <div class="flex items-center gap-4">
                    <div class="logos-pdf">
                        @if($schoolData && $schoolData->report_logo_path)
                            <img src="{{ Storage::url($schoolData->report_logo_path) }}" alt="Logo Reporte"
                                 class="h-14 w-auto object-contain print:h-16"
                                 crossOrigin="anonymous"
                                 onerror="this.style.display='none'">
                        @endif
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 print:text-black print:text-base">
                            {{ $schoolData->name_school ?? 'Unidad Educativa' }}
                        </h1>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 print:text-black print:text-[10px]">
                            {{ __('Libro de Asistencias') }}
                            @if($this->getPeriodLabel())
                                · {{ $this->getPeriodLabel() }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 print:text-black">{{ $this->gradeName }}</p>
                        @if($this->shiftName)
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 print:text-black">{{ $this->shiftName }}</p>
                        @endif
                    </div>
                    <div class="logos-pdf">
                        @if($schoolData && $schoolData->logo_path)
                            <img src="{{ Storage::url($schoolData->logo_path) }}" alt="Logo Institución"
                                 class="h-12 w-auto object-contain print:h-14"
                                 crossOrigin="anonymous"
                                 onerror="this.style.display='none'">
                        @endif
                    </div>
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 print:mb-4">
                <div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/10 p-4 print:border-black print:bg-red-50">
                    <span class="text-2xl font-extrabold text-red-700 dark:text-red-400 print:text-black">{{ $matrix['total_i'] }}</span>
                    <p class="text-xs font-semibold text-red-600 dark:text-red-300 uppercase tracking-wider mt-1 print:text-black">{{ __('Faltas Injustificadas') }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/10 p-4 print:border-black print:bg-emerald-50">
                    <span class="text-2xl font-extrabold text-emerald-700 dark:text-emerald-400 print:text-black">{{ $matrix['total_j'] }}</span>
                    <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-300 uppercase tracking-wider mt-1 print:text-black">{{ __('Faltas Justificadas') }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 print:border-black">
                    <span class="text-2xl font-extrabold text-zinc-900 dark:text-zinc-100 print:text-black">{{ $matrix['student_count'] }}</span>
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mt-1 print:text-black">{{ __('Alumnos') }}</p>
                </div>
            </div>

            {{-- Controls --}}
            <div class="flex flex-col sm:flex-row items-start sm:items-end gap-3 mb-6 p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 no-print">
                <div>
                    <flux:label>{{ __('Periodo') }}</flux:label>
                    <flux:select wire:model.live="filterTrimester" class="min-w-[180px]">
                        <flux:select.option value="">{{ __('Todos (Anual)') }}</flux:select.option>
                        @foreach($trimesters as $period)
                            <flux:select.option value="{{ $period['id'] }}">{{ $period['trimester_name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:label>{{ __('Mes') }}</flux:label>
                    <flux:select wire:model.live="filterMonth" class="min-w-[160px]">
                        <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                        <flux:select.option value="1">{{ __('Enero') }}</flux:select.option>
                        <flux:select.option value="2">{{ __('Febrero') }}</flux:select.option>
                        <flux:select.option value="3">{{ __('Marzo') }}</flux:select.option>
                        <flux:select.option value="4">{{ __('Abril') }}</flux:select.option>
                        <flux:select.option value="5">{{ __('Mayo') }}</flux:select.option>
                        <flux:select.option value="6">{{ __('Junio') }}</flux:select.option>
                        <flux:select.option value="7">{{ __('Julio') }}</flux:select.option>
                        <flux:select.option value="8">{{ __('Agosto') }}</flux:select.option>
                        <flux:select.option value="9">{{ __('Septiembre') }}</flux:select.option>
                        <flux:select.option value="10">{{ __('Octubre') }}</flux:select.option>
                        <flux:select.option value="11">{{ __('Noviembre') }}</flux:select.option>
                        <flux:select.option value="12">{{ __('Diciembre') }}</flux:select.option>
                    </flux:select>
                </div>
                <div class="flex-1">
                    <flux:label>{{ __('Alumno') }}</flux:label>
                    <flux:input wire:model.live.debounce="search" :placeholder="__('Buscar...')" icon="magnifying-glass" />
                </div>
                <flux:button wire:click="downloadPdf" class="shrink-0" variant="danger">
                    {{ __('Exportar PDF') }}
                </flux:button>
            </div>

            {{-- Matrix Table --}}
            @if(count($matrix['students'] ?? []) > 0)
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 print:border-black print:rounded-none">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 print:bg-gray-100">
                                <th rowspan="2" class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 print:text-black min-w-[40px]">N°</th>
                                <th rowspan="2" class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 print:text-black min-w-[200px]">{{ __('Nomina') }}</th>
                                @foreach($subjects as $subject)
                                    <th colspan="2" class="px-2 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400 print:text-black min-w-[60px] text-[11px]">
                                        {{ $subject['subject_name'] }}
                                    </th>
                                @endforeach
                                <th colspan="2" class="px-2 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400 print:text-black bg-zinc-100 dark:bg-zinc-700/50 print:bg-gray-200 min-w-[60px] text-[11px]">{{ __('Total') }}</th>
                            </tr>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 print:bg-gray-100">
                                @foreach($subjects as $subject)
                                    <th class="px-2 py-2 text-center text-[10px] font-bold text-emerald-600 dark:text-emerald-400 print:text-black border-l border-zinc-200 dark:border-zinc-700 print:border-black">J</th>
                                    <th class="px-2 py-2 text-center text-[10px] font-bold text-red-600 dark:text-red-400 print:text-black">I</th>
                                @endforeach
                                <th class="px-2 py-2 text-center text-[10px] font-bold text-emerald-600 dark:text-emerald-400 print:text-black bg-zinc-100 dark:bg-zinc-700/50 print:bg-gray-200 border-l border-zinc-200 dark:border-zinc-700 print:border-black">J</th>
                                <th class="px-2 py-2 text-center text-[10px] font-bold text-red-600 dark:text-red-400 print:text-black bg-zinc-100 dark:bg-zinc-700/50 print:bg-gray-200">I</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($matrix['students'] as $idx => $student)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                    <td class="px-4 py-2 text-zinc-500 text-xs print:text-black">{{ $idx + 1 }}</td>
                                    <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100 text-xs print:text-black">
                                        {{ $student['name'] }}
                                        <span class="block text-[9px] font-mono text-zinc-400 print:text-black">{{ $student['code'] }}</span>
                                    </td>
                                    @foreach($student['subjects'] as $subjectData)
                                        <td class="px-2 py-2 text-center font-bold text-sm {{ $subjectData['j'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-200 dark:text-zinc-700' }} print:text-black">
                                            {{ $subjectData['j'] > 0 ? $subjectData['j'] : '-' }}
                                        </td>
                                        <td class="px-2 py-2 text-center font-bold text-sm {{ $subjectData['i'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-200 dark:text-zinc-700' }} print:text-black">
                                            {{ $subjectData['i'] > 0 ? $subjectData['i'] : '-' }}
                                        </td>
                                    @endforeach
                                    <td class="px-2 py-2 text-center font-bold text-sm text-emerald-600 dark:text-emerald-400 print:text-black bg-zinc-50 dark:bg-zinc-800/50 print:bg-gray-50 border-l border-zinc-200 dark:border-zinc-700 print:border-black">
                                        {{ $student['total_j'] > 0 ? $student['total_j'] : '-' }}
                                    </td>
                                    <td class="px-2 py-2 text-center font-bold text-sm text-red-600 dark:text-red-400 print:text-black bg-zinc-50 dark:bg-zinc-800/50 print:bg-gray-50">
                                        {{ $student['total_i'] > 0 ? $student['total_i'] : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-800/50 font-semibold print:bg-gray-100">
                                <td colspan="2" class="px-4 py-3 text-right text-xs font-bold text-zinc-600 dark:text-zinc-400 print:text-black">{{ __('Totales') }}</td>
                                @foreach($subjects as $subject)
                                    @php $totals = $matrix['totals_by_subject'][$subject['id']] ?? ['j' => 0, 'i' => 0]; @endphp
                                    <td class="px-2 py-3 text-center font-bold text-sm text-emerald-600 dark:text-emerald-400 print:text-black">{{ $totals['j'] > 0 ? $totals['j'] : '-' }}</td>
                                    <td class="px-2 py-3 text-center font-bold text-sm text-red-600 dark:text-red-400 print:text-black">{{ $totals['i'] > 0 ? $totals['i'] : '-' }}</td>
                                @endforeach
                                <td class="px-2 py-3 text-center font-bold text-sm text-emerald-600 dark:text-emerald-400 print:text-black bg-zinc-100 dark:bg-zinc-700/30 print:bg-gray-200">{{ $matrix['total_j'] > 0 ? $matrix['total_j'] : '-' }}</td>
                                <td class="px-2 py-3 text-center font-bold text-sm text-red-600 dark:text-red-400 print:text-black bg-zinc-100 dark:bg-zinc-700/30 print:bg-gray-200">{{ $matrix['total_i'] > 0 ? $matrix['total_i'] : '-' }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Legend --}}
                <div class="flex flex-wrap gap-4 mt-4 px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700 print:border-black print:rounded-none print:bg-gray-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 self-center print:text-black">{{ __('Leyenda:') }}</span>
                    <span class="inline-flex items-center gap-1.5 text-xs">
                        <span class="w-3 h-3 rounded bg-emerald-500 print:bg-emerald-500"></span>
                        J = Falta Justificada
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-xs">
                        <span class="w-3 h-3 rounded bg-red-500 print:bg-red-500"></span>
                        I = Falta Injustificada
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-xs text-zinc-400 print:text-black">
                        <span class="w-3 h-3 rounded bg-zinc-200 dark:bg-zinc-600 print:bg-gray-300"></span>
                        - = Sin registro
                    </span>
                </div>
            @else
                <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700 border-dashed print:hidden">
                    <flux:icon.clipboard-document-list class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-base font-semibold">{{ __('Sin registros de asistencia') }}</p>
                    <p class="text-sm text-zinc-400 mt-1">{{ __('No hay asistencias registradas para este grado.') }}</p>
                </div>
            @endif
        </div>
    @else
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.clipboard-document-list class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('No se encontró asignación de tutoría') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('No se encontró una asignatura de Acompañamiento integral en el aula asociada a su usuario.') }}</p>
        </div>
    @endif
</div>

@push('styles')
<style>
    @media screen {
        .logos-pdf { display: none !important; }
    }
    @media print {
        .no-print { display: none !important; }
        .logos-pdf { display: block !important; }
        body { background: white !important; }
        #contenedorReporte > .report-header {
            border: 1px solid #000 !important;
            border-radius: 0 !important;
        }
        .print\:border-black { border-color: #000 !important; }
        .print\:rounded-none { border-radius: 0 !important; }
        .print\:text-black { color: #000 !important; }
        .print\:bg-gray-100 { background-color: #f3f4f6 !important; }
        .print\:bg-gray-200 { background-color: #e5e7eb !important; }
        .print\:bg-gray-50 { background-color: #f9fafb !important; }
        .print\:bg-red-50 { background-color: #fef2f2 !important; }
        .print\:bg-emerald-50 { background-color: #ecfdf5 !important; }
        .print\:bg-emerald-500 { background-color: #10b981 !important; }
        .print\:bg-red-500 { background-color: #ef4444 !important; }
        .print\:bg-gray-300 { background-color: #d1d5db !important; }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
    }
</style>
@endpush


