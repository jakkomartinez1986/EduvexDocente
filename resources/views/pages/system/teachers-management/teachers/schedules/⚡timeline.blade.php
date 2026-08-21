<?php

use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use App\Services\TeacherManagement\AttendanceService;
use App\Services\TeacherManagement\ClassScheduleService;
use App\Services\TeacherManagement\QuickGradesService;
use App\Actions\TeacherManagement\SaveAttendanceAction;
use App\Actions\TeacherManagement\SaveQuickGradesAction;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Horario del Docente')] class extends Component {
    public ?int $yearId = null;
    public string $selectedDay = '';
    public array $weekDays = [];
    public string $weekStart = '';
    public string $weekEnd = '';
    public array $expandedDays = [];
    public string $activeScheduleType = 'OFFICIAL';
    public array $scheduleTypes = [];
    public array $jornadas = [];
    public string $selectedJornada = 'TODAS';

    public bool $showEvaluationModal = false;
    public ?int $evaluationId = null;
    public string $evaluationDate = '';
    public string $evaluationDateMin = '';
    public array $availableCalendarDays = [];

    public bool $showAttendanceModal = false;
    public ?int $attendanceScheduleId = null;
    public array $attendanceStudents = [];
    public array $attendanceStatuses = [];
    public string $attendanceDate = '';
    public int $attendancePage = 0;
    public string $attendanceClasstopic = '';
    public string $attendanceObservation = '';
    public string $attendanceNovedad = '';
    public string $attendanceNovedadType = '';

    public bool $showQuickGradesModal = false;
    public ?int $quickGradesScheduleId = null;
    public array $quickGradesStudents = [];
    public array $quickGradesValues = [];
    public ?int $quickGradesActivityId = null;
    public string $quickGradesActivityName = '';
    public int $quickGradesPage = 0;
    public array $quickGradesActivities = [];
    public ?int $quickGradesSelectedActivity = null;

    protected function listeners(): array
    {
        return [
            'deleteConfirmed',
        ];
    }

    public function mount(): void
    {
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        $this->weekStart = now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $this->weekEnd = now()->startOfWeek(\Carbon\Carbon::MONDAY)->addDays(4)->toDateString();
        $this->selectedDay = ucfirst(now()->isoFormat('dddd'));
        $this->evaluationDateMin = now()->format('Y-m-d');

        $this->jornadas = app(ClassScheduleService::class)->loadJornadas();
        $this->scheduleTypes = app(ClassScheduleService::class)->getScheduleTypes();
        $this->weekDays = app(ClassScheduleService::class)->buildWeekDays($this->weekStart);
        $this->expandedDays = app(ClassScheduleService::class)->getInitialExpandedDays($this->weekDays);
    }

    public function toggleDay(string $dayName): void
    {
        if (in_array($dayName, $this->expandedDays)) {
            $this->expandedDays = array_values(array_diff($this->expandedDays, [$dayName]));
        } else {
            $this->expandedDays[] = $dayName;
        }
    }

    public function selectDay(string $dayName): void
    {
        $this->selectedDay = $dayName;
    }

    public function selectScheduleType(string $type): void
    {
        $this->activeScheduleType = $type;
    }

    public function selectJornada(string $jornada): void
    {
        $this->selectedJornada = $jornada;
    }

    public function previousWeek(): void
    {
        $start = \Carbon\Carbon::parse($this->weekStart)->subWeek();
        $this->weekStart = $start->toDateString();
        $this->weekEnd = $start->copy()->addDays(4)->toDateString();
        $this->weekDays = app(ClassScheduleService::class)->buildWeekDays($this->weekStart);
        $this->expandedDays = app(ClassScheduleService::class)->getInitialExpandedDays($this->weekDays);
    }

    public function nextWeek(): void
    {
        $start = \Carbon\Carbon::parse($this->weekStart)->addWeek();
        $this->weekStart = $start->toDateString();
        $this->weekEnd = $start->copy()->addDays(4)->toDateString();
        $this->weekDays = app(ClassScheduleService::class)->buildWeekDays($this->weekStart);
        $this->expandedDays = app(ClassScheduleService::class)->getInitialExpandedDays($this->weekDays);
    }

    protected function getSchedules()
    {
        return app(ClassScheduleService::class)->getSchedules(
            $this->yearId,
            auth()->user()->teacher?->id,
            $this->activeScheduleType,
            $this->selectedJornada,
        );
    }

    public function deleteSchedule(int $id): void
    {
        if (app(ClassScheduleService::class)->deleteSchedule($id)) {
            Flux::toast(variant: 'success', text: __('Horario eliminado correctamente.'));
        }
    }

    public function toggleActive(int $id): void
    {
        $isActive = app(ClassScheduleService::class)->toggleScheduleActive($id);
        if ($isActive !== null) {
            Flux::toast(
                variant: 'success',
                text: $isActive ? __('Horario activado.') : __('Horario desactivado.')
            );
        }
    }

    public function activateAll(): void
    {
        app(ClassScheduleService::class)->activateAllSchedules(
            auth()->user()->teacher?->id,
            $this->activeScheduleType,
        );
        Flux::toast(variant: 'success', text: __('Todos los horarios activados.'));
    }

    public function deactivateAll(): void
    {
        app(ClassScheduleService::class)->deactivateAllSchedules(
            auth()->user()->teacher?->id,
            $this->activeScheduleType,
        );
        Flux::toast(variant: 'success', text: __('Todos los horarios desactivados.'));
    }

    public function getSchedulesByDay(): array
    {
        return app(ClassScheduleService::class)->getSchedulesByDay(
            $this->yearId,
            auth()->user()->teacher?->id,
            $this->activeScheduleType,
            $this->selectedJornada,
        );
    }

    public function getStats(): array
    {
        return app(ClassScheduleService::class)->getStats(
            $this->yearId,
            auth()->user()->teacher?->id,
            $this->activeScheduleType,
            $this->selectedJornada,
        );
    }

    public function getDistributivo(): array
    {
        return app(ClassScheduleService::class)->getDistributivo(
            $this->yearId,
            auth()->user()->teacher?->id,
            $this->activeScheduleType,
            $this->selectedJornada,
        );
    }

    public function getTodayAgenda(): \Illuminate\Support\Collection
    {
        return app(ClassScheduleService::class)->getTodayAgenda(
            $this->yearId,
            auth()->user()->teacher?->id,
            $this->activeScheduleType,
            $this->selectedJornada,
        );
    }

    public function getStudentsCountForGrades(array $gradeIds): int
    {
        if (empty($gradeIds) || ! $this->yearId) {
            return 0;
        }

        return StudentEnrollment::whereIn('grade_id', $gradeIds)
            ->where('year_id', $this->yearId)
            ->count();
    }

    // ── Evaluation Modal ──

    public function openEvaluationModal(int $scheduleId): void
    {
        $schedule = ClassSchedule::with('trimester', 'calendarDay')->findOrFail($scheduleId);
        $this->evaluationId = $scheduleId;
        $this->evaluationDate = $schedule->calendarday_id
            ? $schedule->calendarDay->date->format('Y-m-d')
            : now()->format('Y-m-d');

        $query = CalendarDay::where('date', '>=', now()->format('Y-m-d'))
            ->orderBy('date');

        if ($schedule->trimester_id) {
            $query->where('trimester_id', $schedule->trimester_id);
        }

        $this->availableCalendarDays = $query->get()
            ->map(fn ($d) => [
                'id'       => $d->id,
                'date'     => $d->date->format('Y-m-d'),
                'label'    => $d->date->translatedFormat('l, d \d\e F Y'),
                'activity' => $d->activity ?? 'Dia lectivo',
                'period'   => $d->period,
            ])
            ->toArray();

        $this->showEvaluationModal = true;
    }

    public function closeEvaluationModal(): void
    {
        $this->showEvaluationModal = false;
        $this->evaluationId = null;
        $this->evaluationDate = '';
        $this->availableCalendarDays = [];
    }

    public function saveEvaluationDate(): void
    {
        $this->validate([
            'evaluationDate' => 'required|date|after_or_equal:today',
        ], [
            'evaluationDate.required'       => 'Debes seleccionar una fecha.',
            'evaluationDate.after_or_equal' => 'La fecha debe ser hoy o en el futuro.',
        ]);

        $calendarDay = CalendarDay::where('date', $this->evaluationDate)->first();
        $schedule = ClassSchedule::with(['subject', 'trimester'])->findOrFail($this->evaluationId);
        $schedule->update(['calendarday_id' => $calendarDay?->id]);

        if ($calendarDay) {
            $trimestre = $schedule->trimester->trimester_name ?? '';
            $prefijo = $schedule->schedule_type === 'EVALUATION' ? 'Evaluacion' : 'Recuperacion';
            $actividad = "{$prefijo}:".($trimestre ? " ({$trimestre})" : '');
            $calendarDay->update(['activity' => $actividad]);
        }

        $this->closeEvaluationModal();
        Flux::toast(variant: 'success', text: __('Fecha de evaluacion guardada correctamente.'));
    }

    // ── Attendance Modal ──

    public function openAttendanceModal(int $scheduleId): void
    {
        $this->attendanceScheduleId = $scheduleId;
        $this->attendanceDate = now()->format('Y-m-d');

        $this->attendanceStudents = app(AttendanceService::class)->loadStudentsForAttendance(
            $scheduleId,
            $this->yearId,
        );

        $this->attendanceStatuses = app(AttendanceService::class)->loadExistingStatuses(
            $scheduleId,
            $this->attendanceDate,
            $this->attendanceStudents,
        );

        $obs = app(AttendanceService::class)->loadExistingObservation($scheduleId, $this->attendanceDate);
        $this->attendanceClasstopic = $obs['classtopic'];
        $this->attendanceObservation = $obs['observation'];
        $this->attendanceNovedad = $obs['novedad'];
        $this->attendanceNovedadType = $obs['novedad_type'];

        $this->showAttendanceModal = true;
    }

    public function closeAttendanceModal(): void
    {
        $this->showAttendanceModal = false;
        $this->attendanceScheduleId = null;
        $this->attendanceStudents = [];
        $this->attendanceStatuses = [];
        $this->attendanceDate = '';
        $this->attendancePage = 0;
        $this->attendanceClasstopic = '';
        $this->attendanceObservation = '';
        $this->attendanceNovedad = '';
        $this->attendanceNovedadType = '';
    }

    public function getAttendancePaginatedStudents(): array
    {
        return app(AttendanceService::class)->getPaginatedStudents(
            $this->attendanceStudents,
            $this->attendanceStatuses,
            $this->attendancePage,
        );
    }

    public function getAttendanceTotalPages(): int
    {
        return app(AttendanceService::class)->getTotalPages(count($this->attendanceStudents));
    }

    public function prevAttendancePage(): void
    {
        if ($this->attendancePage > 0) {
            $this->attendancePage--;
        }
    }

    public function nextAttendancePage(): void
    {
        if ($this->attendancePage < $this->getAttendanceTotalPages() - 1) {
            $this->attendancePage++;
        }
    }

    public function goToAttendancePage(int $page): void
    {
        $totalPages = $this->getAttendanceTotalPages();
        $this->attendancePage = max(0, min($page, $totalPages - 1));
    }

    public function setAttendanceStatus(int $studentId, string $status): void
    {
        $this->attendanceStatuses[$studentId] = $status;
    }

    public function markAllPresent(): void
    {
        $this->attendanceStatuses = app(AttendanceService::class)->markAllPresent($this->attendanceStudents);
    }

    public function saveAttendance(): void
    {
        app(SaveAttendanceAction::class)->handle(
            scheduleId: $this->attendanceScheduleId,
            date: $this->attendanceDate,
            yearId: $this->yearId,
            userId: auth()->id(),
            statuses: $this->attendanceStatuses,
            classtopic: $this->attendanceClasstopic,
            observation: $this->attendanceObservation,
            novedad: $this->attendanceNovedad,
            novedadType: $this->attendanceNovedadType,
        );

        $this->closeAttendanceModal();
        Flux::toast(variant: 'success', text: __('Asistencia guardada correctamente.'));
    }

    // ── Quick Grades Modal ──

    public function openQuickGradesModal(int $scheduleId): void
    {
        $this->quickGradesScheduleId = $scheduleId;

        $this->quickGradesStudents = app(QuickGradesService::class)->loadStudentsForQuickGrades(
            $scheduleId,
            $this->yearId,
        );

        $this->quickGradesActivities = app(QuickGradesService::class)->loadActivities(
            $scheduleId,
            $this->yearId,
        );

        $defaultActivity = app(QuickGradesService::class)->getDefaultActivity($this->quickGradesActivities);

        if ($defaultActivity) {
            $this->quickGradesSelectedActivity = $defaultActivity['id'];
            $this->quickGradesActivityId = $defaultActivity['id'];
            $this->quickGradesActivityName = $defaultActivity['name'];
            $this->quickGradesValues = app(QuickGradesService::class)->loadGradesForActivity(
                $defaultActivity['id'],
                $this->quickGradesStudents,
            );
        } else {
            $this->quickGradesSelectedActivity = null;
            $this->quickGradesActivityId = null;
            $this->quickGradesActivityName = empty($this->quickGradesActivities) ? 'Sin bloque configurado' : 'Sin actividad';
            $this->quickGradesValues = collect($this->quickGradesStudents)
                ->mapWithKeys(fn ($s) => [$s['id'] => ''])
                ->toArray();
        }

        $this->showQuickGradesModal = true;
    }

    public function selectQuickGradesActivity(int $activityId): void
    {
        $activity = collect($this->quickGradesActivities)->firstWhere('id', $activityId);
        if (! $activity) {
            return;
        }

        $this->quickGradesSelectedActivity = $activityId;
        $this->quickGradesActivityId = $activity['id'];
        $this->quickGradesActivityName = $activity['name'];
        $this->quickGradesValues = app(QuickGradesService::class)->loadGradesForActivity(
            $activity['id'],
            $this->quickGradesStudents,
        );
    }

    public function closeQuickGradesModal(): void
    {
        $this->showQuickGradesModal = false;
        $this->quickGradesScheduleId = null;
        $this->quickGradesStudents = [];
        $this->quickGradesValues = [];
        $this->quickGradesActivityId = null;
        $this->quickGradesActivityName = '';
        $this->quickGradesPage = 0;
        $this->quickGradesActivities = [];
        $this->quickGradesSelectedActivity = null;
    }

    public function getQuickGradesPaginatedStudents(): array
    {
        return app(QuickGradesService::class)->getPaginatedStudents(
            $this->quickGradesStudents,
            $this->quickGradesPage,
        );
    }

    public function getQuickGradesTotalPages(): int
    {
        return app(QuickGradesService::class)->getTotalPages(count($this->quickGradesStudents));
    }

    public function prevQuickGradesPage(): void
    {
        if ($this->quickGradesPage > 0) {
            $this->quickGradesPage--;
        }
    }

    public function nextQuickGradesPage(): void
    {
        if ($this->quickGradesPage < $this->getQuickGradesTotalPages() - 1) {
            $this->quickGradesPage++;
        }
    }

    public function goToQuickGradesPage(int $page): void
    {
        $totalPages = $this->getQuickGradesTotalPages();
        $this->quickGradesPage = max(0, min($page, $totalPages - 1));
    }

    public function saveQuickGrades(): void
    {
        if (! $this->quickGradesActivityId) {
            Flux::toast(variant: 'danger', text: __('No hay actividad configurada para registrar calificaciones.'));
            return;
        }

        app(SaveQuickGradesAction::class)->handle(
            activityId: $this->quickGradesActivityId,
            values: $this->quickGradesValues,
            userId: auth()->id(),
        );

        $this->closeQuickGradesModal();
        Flux::toast(variant: 'success', text: __('Calificaciones guardadas correctamente.'));
    }
}; ?>

@php
    $horariosPorDia = $this->getSchedulesByDay();
    $stats = $this->getStats();
    $distributivo = $this->getDistributivo();
    $agenda = $this->getTodayAgenda();
@endphp

<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Horario del Docente') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Vista semanal de tu horario de clases') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.teacher.schedule.create') }}" wire:navigate variant="primary">
            <flux:icon.plus /> {{ __('Nuevo Horario') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Horario del Docente') }}</span>
    </nav>

    @include('pages.system.teachers-management.teachers.schedules.partials.controls-bar')

    @include('pages.system.teachers-management.teachers.schedules.partials.status-panel')

    @include('pages.system.teachers-management.teachers.schedules.partials.day-card')

    @include('pages.system.teachers-management.teachers.schedules.partials.today-summary')

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
        @include('pages.system.teachers-management.teachers.schedules.partials.stats-panel')

        @include('pages.system.teachers-management.teachers.schedules.partials.distributive-table')
    </div>

    @include('pages.system.teachers-management.teachers.schedules.partials.evaluation-modal')

    @include('pages.system.teachers-management.teachers.schedules.partials.attendance-modal')

    @include('pages.system.teachers-management.teachers.schedules.partials.quick-grades-modal')
</div>
