<?php

declare(strict_types=1);

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Identity\Users\Teacher;
use App\Models\Incidents\IncidentIntervention;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\School;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Models\StudentManagement\Academics\HomeworkPending;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Services\AcademicYearService;
use App\Services\SchoolConfigService;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public ?int $yearId = null;
    public ?Teacher $teacher = null;
    public bool $isTutor = false;
    public ?int $tutorGradeId = null;
    public ?string $tutorGradeName = null;
    public string $todayLabel = '';

    // Stats
    public int $stat1 = 0;
    public string $stat1Label = '';
    public string $stat1Icon = '';
    public string $stat1Color = '';

    public int $stat2 = 0;
    public string $stat2Label = '';
    public string $stat2Icon = '';
    public string $stat2Color = '';

    public int $stat3 = 0;
    public string $stat3Label = '';
    public string $stat3Icon = '';
    public string $stat3Color = '';

    public int $stat4 = 0;
    public string $stat4Label = '';
    public string $stat4Icon = '';
    public string $stat4Color = '';

    // Data arrays
    public array $lowPerformanceStudents = [];
    public array $upcomingAppointments = [];
    public array $mySubjects = [];
    public array $recentActivity = [];
    public array $quickLinks = [];

    public function mount(): void
    {
        $this->teacher = auth()->user()->teacher;
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        $this->todayLabel = now()->locale(config('app.locale'))->translatedFormat('l, d \de F \de Y');

        if (! $this->teacher || ! $this->yearId) {
            return;
        }

        $this->detectTutor();
        $this->loadStats();

        if ($this->isTutor) {
            $this->loadLowPerformance();
            $this->loadUpcomingAppointments();
        }

        $this->loadMySubjects();
        $this->loadRecentActivity();
        $this->loadQuickLinks();
    }

    protected function detectTutor(): void
    {
        $tutorSchedule = ClassSchedule::where('teacher_id', $this->teacher->id)
            ->where('year_id', $this->yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade')
            ->first();

        if (! $tutorSchedule) {
            $this->isTutor = false;
            $this->tutorGradeId = null;
            $this->tutorGradeName = null;

            return;
        }

        $this->isTutor = true;
        $this->tutorGradeId = $tutorSchedule->grade_id;
        $this->tutorGradeName = trim(($tutorSchedule->grade->grade_name ?? '') . ' ' . ($tutorSchedule->grade->section ?? ''));
    }

    protected function loadStats(): void
    {
        if ($this->isTutor) {
            $this->stat1 = StudentEnrollment::where('grade_id', $this->tutorGradeId)
                ->where('year_id', $this->yearId)
                ->where('status', 'active')
                ->count();

            $this->stat2 = Attendance::where('tutor_id', $this->teacher->id)
                ->whereDate('date', Carbon::today())
                ->whereIn('status', ['I', 'A'])
                ->count();

            $this->stat3 = AcademicNotification::where('teacher_id', $this->teacher->id)
                ->where('year_id', $this->yearId)
                ->whereNull('sent_at')
                ->count();

            $this->stat4 = IncidentIntervention::where('teacher_id', $this->teacher->id)
                ->where('year_id', $this->yearId)
                ->where('status', 'pending')
                ->count();

            $this->stat1Label = __('Tutorados');
            $this->stat1Icon = 'user-group';
            $this->stat1Color = 'blue';

            $this->stat2Label = __('Ausentes hoy');
            $this->stat2Icon = 'exclamation-triangle';
            $this->stat2Color = 'red';

            $this->stat3Label = __('Notificaciones pendientes');
            $this->stat3Icon = 'bell';
            $this->stat3Color = 'amber';

            $this->stat4Label = __('Incidencias abiertas');
            $this->stat4Icon = 'flag';
            $this->stat4Color = 'purple';

            return;
        }

        $stats = app(\App\Services\TeacherManagement\DashboardStatsCache::class)->counts(
            (int) $this->teacher->id,
            (int) $this->yearId,
            function (): array {
                $scheduleIds = ClassSchedule::where('teacher_id', $this->teacher->id)
                    ->where('year_id', $this->yearId)
                    ->where('is_active', true)
                    ->pluck('id');

                $attendanceStudentIds = Attendance::whereIn('class_schedule_id', $scheduleIds)->pluck('student_id');

                $activityStudentIds = ActivityGrade::whereHas('activity.assessmentBlock', function ($q) {
                    $q->where('teacher_id', $this->teacher->id)
                        ->where(fn ($year) => $year->whereNull('year_id')->orWhere('year_id', $this->yearId));
                })->pluck('student_id');

                return [
                    1 => $scheduleIds->count(),
                    2 => $attendanceStudentIds->merge($activityStudentIds)->unique()->count(),
                    3 => HomeworkPending::where('teacher_id', $this->teacher->id)
                        ->where('year_id', $this->yearId)
                        ->where('status', 'pending')
                        ->count(),
                    4 => AcademicNotification::where('teacher_id', $this->teacher->id)
                        ->where('year_id', $this->yearId)
                        ->whereNotNull('sent_at')
                        ->count(),
                ];
            },
        );

        $this->stat1 = $stats[1];
        $this->stat2 = $stats[2];
        $this->stat3 = $stats[3];
        $this->stat4 = $stats[4];

        $this->stat1Label = __('Clases activas');
        $this->stat1Icon = 'academic-cap';
        $this->stat1Color = 'blue';

        $this->stat2Label = __('Estudiantes');
        $this->stat2Icon = 'users';
        $this->stat2Color = 'emerald';

        $this->stat3Label = __('Tareas pendientes');
        $this->stat3Icon = 'document-text';
        $this->stat3Color = 'amber';

        $this->stat4Label = __('Notificaciones enviadas');
        $this->stat4Icon = 'paper-airplane';
        $this->stat4Color = 'purple';
    }

    protected function loadLowPerformance(): void
    {
        $studentIds = StudentEnrollment::where('grade_id', $this->tutorGradeId)
            ->where('year_id', $this->yearId)
            ->where('status', 'active')
            ->pluck('student_id');

        if ($studentIds->isEmpty()) {
            return;
        }

        $lowGrades = ActivityGrade::query()
            ->where('grade', '<', 7)
            ->whereIn('student_id', $studentIds)
            ->whereHas('activity.assessmentBlock', function ($q) {
                $q->where('teacher_id', $this->teacher->id)
                    ->where(fn ($year) => $year->whereNull('year_id')->orWhere('year_id', $this->yearId));
            })
            ->with(['activity.assessmentBlock.subject', 'student.user'])
            ->get()
            ->sortByDesc(fn ($row) => $row->activity?->date?->getTimestamp() ?? 0);

        $this->lowPerformanceStudents = $lowGrades
            ->groupBy('student_id')
            ->take(5)
            ->map(function ($rows) {
                $latest = $rows->first();

                return [
                    'name' => $latest->student?->user?->fullname ?? '-',
                    'subject' => $latest->activity?->assessmentBlock?->subject?->subject_name ?? '-',
                    'grade' => number_format((float) $latest->grade, 2),
                ];
            })
            ->values()
            ->toArray();
    }

    protected function loadUpcomingAppointments(): void
    {
        $notifications = AcademicNotification::where('teacher_id', $this->teacher->id)
            ->whereNotNull('appointment_date')
            ->where('appointment_date', '>=', Carbon::today())
            ->with(['student.user'])
            ->orderBy('appointment_date')
            ->limit(5)
            ->get();

        $this->upcomingAppointments = $notifications
            ->map(fn ($notification) => [
                'name' => $notification->student?->user?->fullname ?? '-',
                'date' => $notification->appointment_date?->format('d/m/Y') ?? '-',
                'time' => $notification->appointment_time?->format('H:i'),
                'type' => $notification->type,
                'sent' => $notification->sent_at !== null,
            ])
            ->toArray();
    }

    protected function loadMySubjects(): void
    {
        $dayOrder = ['LUNES' => 1, 'MARTES' => 2, 'MIERCOLES' => 3, 'JUEVES' => 4, 'VIERNES' => 5, 'SABADO' => 6];

        $schedules = ClassSchedule::where('teacher_id', $this->teacher->id)
            ->where('year_id', $this->yearId)
            ->where('is_active', true)
            ->with(['subject', 'grade'])
            ->get()
            ->sortBy(fn ($row) => ($dayOrder[$row->day] ?? 9) . ' ' . ($row->start_time?->format('H:i') ?? ''));

        $this->mySubjects = $schedules
            ->groupBy(fn ($schedule) => $schedule->subject_id . '-' . $schedule->grade_id)
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'subject' => $first->subject?->subject_name ?? '-',
                    'grade' => trim(($first->grade?->grade_name ?? '') . ' ' . ($first->grade?->section ?? '')),
                    'schedule' => $rows
                        ->map(fn ($row) => $this->getDayLabel($row->day) . ' ' . ($row->start_time?->format('H:i') ?? '') . '-' . ($row->end_time?->format('H:i') ?? ''))
                        ->implode(', '),
                    'classroom' => $rows->first(fn ($row) => filled($row->classroom))?->classroom ?? '—',
                ];
            })
            ->values()
            ->toArray();
    }

    protected function getDayLabel(string $day): string
    {
        return match ($day) {
            'LUNES' => 'Lunes',
            'MARTES' => 'Martes',
            'MIERCOLES' => 'Miércoles',
            'JUEVES' => 'Jueves',
            'VIERNES' => 'Viernes',
            'SABADO' => 'Sábado',
            default => $day,
        };
    }

    protected function loadRecentActivity(): void
    {
        $notifications = AcademicNotification::where('teacher_id', $this->teacher->id)
            ->where('year_id', $this->yearId)
            ->with('student.user')
            ->orderByDesc('generated_date')
            ->limit(5)
            ->get()
            ->map(fn ($notification) => [
                'icon' => 'bell',
                'color' => 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30',
                'description' => __('Notificación de :type para :name', ['type' => $notification->type, 'name' => $notification->student?->user?->fullname ?? '-']),
                'date' => $notification->generated_date ?? $notification->created_at,
            ]);

        $interventions = IncidentIntervention::where('teacher_id', $this->teacher->id)
            ->where('year_id', $this->yearId)
            ->with('student.user')
            ->orderByDesc('date')
            ->limit(5)
            ->get()
            ->map(fn ($intervention) => [
                'icon' => 'flag',
                'color' => 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30',
                'description' => __('Incidencia de :type con :name', ['type' => $intervention->type, 'name' => $intervention->student?->user?->fullname ?? '-']),
                'status' => $intervention->status,
                'date' => $intervention->date,
            ]);

        $observations = ClassObservation::where('year_id', $this->yearId)
            ->where(function ($q) {
                $q->where('teacher_id', $this->teacher->id)->orWhere('tutor_id', $this->teacher->id);
            })
            ->orderByDesc('observation_date')
            ->limit(5)
            ->get()
            ->map(fn ($observation) => [
                'icon' => 'clipboard-document-list',
                'color' => 'text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30',
                'description' => __('Observación de clase: :topic', ['topic' => $observation->classtopic ?? $observation->observation]),
                'date' => $observation->observation_date,
            ]);

        $this->recentActivity = $notifications
            ->merge($interventions)
            ->merge($observations)
            ->sortByDesc(fn ($item) => $item['date']?->getTimestamp() ?? 0)
            ->take(10)
            ->map(fn ($item) => [
                'icon' => $item['icon'],
                'color' => $item['color'],
                'description' => $item['description'],
                'status' => $item['status'] ?? null,
                'date' => $item['date']?->format('d/m/Y'),
            ])
            ->values()
            ->toArray();
    }

    protected function loadQuickLinks(): void
    {
        $this->quickLinks = $this->isTutor ? [
            ['label' => __('Tutorados'), 'url' => route('admin.teacher.tutor-students.index'), 'icon' => 'user-group'],
            ['label' => __('Asistencias'), 'url' => route('admin.teacher.tutor-attendance-book.index'), 'icon' => 'clipboard-document-list'],
            ['label' => __('Reportes'), 'url' => route('admin.teacher.tutor-grade-reports.index'), 'icon' => 'chart-bar'],
            ['label' => __('Notificaciones'), 'url' => route('admin.teacher.notifications.index'), 'icon' => 'bell'],
            ['label' => __('Incidencias'), 'url' => route('admin.teacher.incidents.index'), 'icon' => 'flag'],
        ] : [
            ['label' => __('Horario'), 'url' => route('admin.teacher.schedule.timeline'), 'icon' => 'calendar-days'],
            ['label' => __('Libro Asistencias'), 'url' => route('admin.teacher.attendance-book.index'), 'icon' => 'clipboard-document-list'],
            ['label' => __('Calificaciones'), 'url' => route('admin.summaries.gradebook.index'), 'icon' => 'chart-bar'],
            ['label' => __('Incidencias'), 'url' => route('admin.teacher.incidents.index'), 'icon' => 'flag'],
            ['label' => __('Notificaciones'), 'url' => route('admin.teacher.notifications.index'), 'icon' => 'bell'],
        ];
    }

    public function getStatsProperty(): array
    {
        return [
            ['value' => $this->stat1, 'label' => $this->stat1Label, 'icon' => $this->stat1Icon, 'color' => $this->stat1Color],
            ['value' => $this->stat2, 'label' => $this->stat2Label, 'icon' => $this->stat2Icon, 'color' => $this->stat2Color],
            ['value' => $this->stat3, 'label' => $this->stat3Label, 'icon' => $this->stat3Icon, 'color' => $this->stat3Color],
            ['value' => $this->stat4, 'label' => $this->stat4Label, 'icon' => $this->stat4Icon, 'color' => $this->stat4Color],
        ];
    }

    public function getCurrentSchoolProperty(): ?School
    {
        return app(SchoolConfigService::class)->getActiveSchool();
    }

    public function getStatColorClasses(string $color): string
    {
        return match ($color) {
            'blue' => 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400',
            'emerald' => 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400',
            'amber' => 'bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400',
            'red' => 'bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400',
            default => 'bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400',
        };
    }

    public function getInterventionStatusBadge(string $status): array
    {
        return match ($status) {
            'completed' => ['label' => __('Completada'), 'color' => 'green'],
            'programmed' => ['label' => __('Programada'), 'color' => 'blue'],
            default => ['label' => __('Pendiente'), 'color' => 'amber'],
        };
    }
}; ?>

<div>
        @if (! $this->teacher || ! $this->yearId)
            <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:icon.academic-cap class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
                <p class="text-base font-semibold">{{ __('No se pudo cargar el dashboard') }}</p>
                <p class="text-sm text-zinc-400 mt-1">{{ __('No se encontró un docente asociado a su usuario o un año lectivo activo.') }}</p>
            </div>
        @else
            {{-- Section 1: Welcome banner --}}
            <div class="mb-6 px-6 py-5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-zinc-900 dark:to-zinc-800">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <flux:heading size="xl">{{ __('Bienvenido/a, :name', ['name' => $this->teacher->user?->fullname]) }}</flux:heading>
                        <div class="flex items-center gap-3 mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            <span>{{ $todayLabel }}</span>
                            <span class="hidden sm:inline">•</span>
                            <span>{{ $this->currentSchool?->name_school ?? __('Unidad Educativa') }}</span>
                        </div>
                    </div>
                    @if ($isTutor && $tutorGradeName)
                        <flux:badge color="fuchsia" size="lg" icon="academic-cap">
                            {{ __('Tutor de :grade', ['grade' => $tutorGradeName]) }}
                        </flux:badge>
                    @endif
                </div>
            </div>

            {{-- Section 2: Stats cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                @foreach ($this->stats as $stat)
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center mb-3 {{ $this->getStatColorClasses($stat['color']) }}">
                            <flux:icon :name="$stat['icon']" class="size-5" />
                        </div>
                        <div class="text-2xl font-bold text-zinc-900 dark:text-white font-mono">{{ $stat['value'] }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>

            @if ($isTutor)
                {{-- Section 3: Tutorados con bajo rendimiento --}}
                <flux:card class="mb-6">
                    <flux:heading level="2" class="mb-4">{{ __('Tutorados con bajo rendimiento') }}</flux:heading>
                    @if (count($lowPerformanceStudents) > 0)
                        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estudiante') }}</th>
                                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Asignatura') }}</th>
                                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Nota') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach ($lowPerformanceStudents as $item)
                                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $item['name'] }}</td>
                                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $item['subject'] }}</td>
                                            <td class="px-4 py-3 text-right">
                                                <flux:badge color="red" size="sm">{{ $item['grade'] }}</flux:badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="flex items-center gap-3 px-4 py-6 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800">
                            <flux:icon.check-circle class="size-6 text-emerald-600 dark:text-emerald-400" />
                            <flux:text>{{ __('Todos los tutorados tienen buen rendimiento') }}</flux:text>
                        </div>
                    @endif
                </flux:card>

                {{-- Section 4: Próximas citas --}}
                <flux:card class="mb-6">
                    <flux:heading level="2" class="mb-4">{{ __('Próximas citas') }}</flux:heading>
                    @if (count($upcomingAppointments) > 0)
                        <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($upcomingAppointments as $appointment)
                                <li class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                                            <flux:icon.calendar-days class="size-4 text-indigo-600 dark:text-indigo-400" />
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $appointment['name'] }}</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $appointment['date'] }}@if($appointment['time']) · {{ $appointment['time'] }}@endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <flux:badge :color="match($appointment['type']) { 'asistencia' => 'amber', 'comportamental' => 'red', default => 'blue' }" size="sm">
                                            {{ ucfirst($appointment['type']) }}
                                        </flux:badge>
                                        <flux:badge :color="$appointment['sent'] ? 'green' : 'zinc'" size="sm">
                                            {{ $appointment['sent'] ? __('Enviada') : __('Pendiente') }}
                                        </flux:badge>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="flex items-center gap-3 px-4 py-6 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                            <flux:icon.calendar-days class="size-6 text-zinc-400 dark:text-zinc-500" />
                            <flux:text>{{ __('No hay citas programadas') }}</flux:text>
                        </div>
                    @endif
                </flux:card>
            @endif

            {{-- Section 5: Mis materias --}}
            <flux:card class="mb-6">
                <flux:heading level="2" class="mb-4">{{ __('Mis materias') }}</flux:heading>
                @if (count($mySubjects) > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        @foreach ($mySubjects as $subject)
                            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 hover:border-blue-300 dark:hover:border-blue-700 transition">
                                <div class="flex items-start gap-3">
                                    <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/30 shrink-0">
                                        <flux:icon.book-open class="size-4 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $subject['subject'] }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $subject['grade'] }}</div>
                                        <div class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">{{ $subject['schedule'] }}</div>
                                        <div class="mt-1 flex items-center gap-1 text-xs text-zinc-400 dark:text-zinc-500">
                                            <flux:icon.map-pin class="size-3.5" />
                                            {{ __('Aula') }}: {{ $subject['classroom'] }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex items-center gap-3 px-4 py-6 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                        <flux:icon.book-open class="size-6 text-zinc-400 dark:text-zinc-500" />
                        <flux:text>{{ __('No tienes materias asignadas este año lectivo') }}</flux:text>
                    </div>
                @endif
            </flux:card>

            {{-- Section 6: Actividad reciente --}}
            <flux:card class="mb-6">
                <flux:heading level="2" class="mb-4">{{ __('Actividad reciente') }}</flux:heading>
                @if (count($recentActivity) > 0)
                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($recentActivity as $item)
                            <li class="flex items-start justify-between gap-3 py-3">
                                <div class="flex items-start gap-3 min-w-0">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg shrink-0 {{ $item['color'] }}">
                                        <flux:icon :name="$item['icon']" class="size-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm text-zinc-900 dark:text-zinc-100">{{ $item['description'] }}</div>
                                        @if ($item['status'])
                                            <flux:badge :color="$this->getInterventionStatusBadge($item['status'])['color']" size="xs" class="mt-1">
                                                {{ $this->getInterventionStatusBadge($item['status'])['label'] }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                </div>
                                <span class="text-xs text-zinc-400 dark:text-zinc-500 whitespace-nowrap">{{ $item['date'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="flex items-center gap-3 px-4 py-6 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                        <flux:icon.clock class="size-6 text-zinc-400 dark:text-zinc-500" />
                        <flux:text>{{ __('Sin actividad reciente') }}</flux:text>
                    </div>
                @endif
            </flux:card>

            {{-- Section 7: Quick access --}}
            <flux:card>
                <flux:heading level="2" class="mb-4">{{ __('Accesos rápidos') }}</flux:heading>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach ($quickLinks as $link)
                        <flux:button href="{{ $link['url'] }}" wire:navigate variant="outline" class="justify-start!">
                            <div class="flex items-center gap-2">
                                <flux:icon :name="$link['icon']" class="size-4" />
                                <span>{{ $link['label'] }}</span>
                            </div>
                        </flux:button>
                    @endforeach
                </div>
            </flux:card>
        @endif
    </div>
</div>
