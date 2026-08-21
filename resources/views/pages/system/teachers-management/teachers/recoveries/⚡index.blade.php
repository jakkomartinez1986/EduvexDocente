<?php

declare(strict_types=1);

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Supplementary\ExamRecovery;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\StudentManagement\Academics\HomeworkPending;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Recuperaciones')] class extends Component {
    public ?int $yearId = null;
    public mixed $selectedTrimesterId = null;
    public ?int $selectedGradeId = null;
    public ?int $selectedSubjectId = null;
    public ?int $selectedBlockId = null;
    public ?int $selectedActivityId = null;
    public string $selectedType = 'activity';

    public array $schedules = [];
    public array $trimesters = [];
    public array $grades = [];
    public array $subjects = [];
    public array $blocks = [];
    public array $activities = [];

    public float $passingGrade = 7;
    public float $examMaxScore = 20;

    public array $recoveryGrade = [];
    public array $recoveryMethod = [];

    public string $activeTab = 'register';
    public int $appliedTrimesterId = 0;
    public array $appliedActivityRecoveries = [];
    public array $appliedExamRecoveries = [];

    public function mount(): void
    {
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();

        $this->schedules = ClassSchedule::where('teacher_id', auth()->user()->teacher?->id)
            ->where('year_id', $this->yearId)
            ->with('grade.nivel.shift', 'subject')
            ->get()
            ->toArray();

        $this->loadTrimesters();
        $this->loadGrades();

        if (! $this->selectedTrimesterId && count($this->trimesters) > 0) {
            $this->selectedTrimesterId = $this->trimesters[0]['id'];
        }

        if (! $this->selectedGradeId && count($this->grades) > 0) {
            $this->selectedGradeId = $this->grades[0]['id'];
            $this->loadSubjectsForGrade();
        }

        if (! $this->selectedSubjectId && count($this->subjects) > 0) {
            $this->selectedSubjectId = $this->subjects[0]['id'];
        }

        $this->loadBlocks();

        if (! $this->selectedBlockId && count($this->blocks) > 0) {
            $this->selectedBlockId = $this->blocks[0]['id'];
        }

        $this->loadActivities();

        $this->appliedTrimesterId = $this->selectedTrimesterId ?? (count($this->trimesters) > 0 ? $this->trimesters[0]['id'] : 0);
        $this->loadAppliedRecoveries();
    }

    protected function loadTrimesters(): void
    {
        $currentYear = app(AcademicYearService::class)->getActiveYear();

        if (! $currentYear) {
            $this->trimesters = [];

            return;
        }

        $this->trimesters = $currentYear->academicPeriods()
            ->where('status', 1)
            ->where('is_supletorio', false)
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    protected function loadGrades(): void
    {
        $this->grades = collect($this->schedules)
            ->pluck('grade')
            ->unique('id')
            ->values()
            ->all();
    }

    protected function loadSubjectsForGrade(): void
    {
        if (! $this->selectedGradeId) {
            $this->subjects = [];

            return;
        }

        $this->subjects = collect($this->schedules)
            ->where('grade_id', $this->selectedGradeId)
            ->pluck('subject')
            ->unique('id')
            ->values()
            ->all();
    }

    protected function loadBlocks(): void
    {
        if (! $this->selectedSubjectId || ! $this->selectedGradeId || ! $this->selectedTrimesterId) {
            $this->blocks = [];

            return;
        }

        $this->blocks = AssessmentBlock::where('year_id', $this->yearId)
            ->where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $this->selectedTrimesterId)
            ->where('teacher_id', auth()->user()->teacher?->id)
            ->orderBy('order')
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    protected function loadActivities(): void
    {
        $this->recoveryGrade = [];
        $this->recoveryMethod = [];
        $this->selectedType = 'activity';

        if (! $this->selectedBlockId) {
            $this->activities = [];
            $this->selectedActivityId = null;

            return;
        }

        $this->activities = Activity::where('assessment_block_id', $this->selectedBlockId)
            ->where('status', true)
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->toArray();

        if (! in_array($this->selectedActivityId, array_column($this->activities, 'id'))) {
            $this->selectedActivityId = null;
        }
    }

    public function updatedSelectedTrimesterId(): void
    {
        $this->selectedBlockId = null;
        $this->selectedActivityId = null;
        $this->selectedType = 'activity';
        $this->loadBlocks();
        $this->loadActivities();
    }

    public function updatedSelectedGradeId(): void
    {
        $this->selectedSubjectId = null;
        $this->selectedBlockId = null;
        $this->selectedActivityId = null;
        $this->selectedType = 'activity';
        $this->loadSubjectsForGrade();

        if (count($this->subjects) === 1) {
            $this->selectedSubjectId = $this->subjects[0]['id'];
        }

        $this->loadBlocks();
        $this->loadActivities();
    }

    public function updatedSelectedSubjectId(): void
    {
        $this->selectedBlockId = null;
        $this->selectedActivityId = null;
        $this->selectedType = 'activity';
        $this->loadBlocks();
        $this->loadActivities();
    }

    public function updatedSelectedBlockId(): void
    {
        $this->selectedActivityId = null;
        $this->selectedType = 'activity';
        $this->loadActivities();
    }

    public function selectExam(): void
    {
        $this->selectedActivityId = null;
        $this->selectedType = 'exam';
        $this->recoveryGrade = [];
        $this->recoveryMethod = [];
    }

    public function selectActivity(int $activityId): void
    {
        $this->selectedActivityId = $activityId;
        $this->selectedType = 'activity';
        $this->recoveryGrade = [];
        $this->recoveryMethod = [];
    }

    public function loadAppliedRecoveries(): void
    {
        $this->appliedActivityRecoveries = [];
        $this->appliedExamRecoveries = [];

        if (! $this->appliedTrimesterId || ! $this->yearId) {
            return;
        }

        $this->appliedActivityRecoveries = ActivityRecovery::where('is_applied', true)
            ->whereHas('activity', function ($q) {
                $q->whereHas('assessmentBlock', function ($q2) {
                    $q2->where('trimester_id', $this->appliedTrimesterId)
                        ->where('year_id', $this->yearId)
                        ->where('teacher_id', auth()->user()->teacher?->id);
                });
            })
            ->with('student.user', 'activity')
            ->orderBy('applied_at', 'desc')
            ->get()
            ->toArray();

        $this->appliedExamRecoveries = ExamRecovery::where('is_applied', true)
            ->where('trimester_id', $this->appliedTrimesterId)
            ->where('year_id', $this->yearId)
            ->with('student.user')
            ->orderBy('applied_at', 'desc')
            ->get()
            ->toArray();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;

        if ($tab === 'applied') {
            $this->loadAppliedRecoveries();
        }
    }

    public function updatedAppliedTrimesterId(): void
    {
        $this->loadAppliedRecoveries();
    }

    public function getExamRecoverableStudentsProperty()
    {
        if ($this->selectedType !== 'exam' || ! $this->selectedGradeId || ! $this->selectedSubjectId || ! $this->selectedTrimesterId) {
            return collect();
        }

        $studentIds = StudentEnrollment::where('grade_id', $this->selectedGradeId)
            ->where('year_id', $this->yearId)
            ->where('status', 'active')
            ->pluck('student_id');

        $examGrades = StudentExam::where('year_id', $this->yearId)
            ->where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $this->selectedTrimesterId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $recoveries = ExamRecovery::where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $this->selectedTrimesterId)
            ->where('year_id', $this->yearId)
            ->whereIn('student_id', $studentIds)
            ->orderBy('attempt_number')
            ->get()
            ->groupBy('student_id');

        $students = Student::whereIn('id', $studentIds)
            ->with('user')
            ->orderBy(
                \App\Models\User::select('lastname')
                    ->whereColumn('users.id', 'students.user_id')
            )
            ->get();

        return $students
            ->filter(function ($student) use ($examGrades, $recoveries) {
                $grade = $examGrades->get($student->id)?->grade;

                return ($grade !== null && (float) $grade < $this->passingGrade)
                    || $recoveries->has($student->id);
            })
            ->map(function ($student) use ($examGrades, $recoveries) {
                $grade = $examGrades->get($student->id)?->grade;

                return (object) [
                    'student' => $student,
                    'current_grade' => $grade !== null ? (float) $grade : null,
                    'recoveries' => $recoveries->get($student->id, collect()),
                    'recovery_count' => $recoveries->get($student->id)?->count() ?? 0,
                ];
            })
            ->values();
    }

    public function getRecoverableStudentsProperty()
    {
        if ($this->selectedType === 'exam') {
            return $this->examRecoverableStudents;
        }

        if (! $this->selectedActivityId || ! $this->selectedGradeId) {
            return collect();
        }

        $studentIds = StudentEnrollment::where('grade_id', $this->selectedGradeId)
            ->where('year_id', $this->yearId)
            ->where('status', 'active')
            ->pluck('student_id');

        $grades = ActivityGrade::where('activity_id', $this->selectedActivityId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $recoveries = ActivityRecovery::where('activity_id', $this->selectedActivityId)
            ->whereIn('student_id', $studentIds)
            ->orderBy('attempt_number')
            ->get()
            ->groupBy('student_id');

        $students = Student::whereIn('id', $studentIds)
            ->with('user')
            ->orderBy(
                \App\Models\User::select('lastname')
                    ->whereColumn('users.id', 'students.user_id')
            )
            ->get();

        return $students
            ->filter(function ($student) use ($grades, $recoveries) {
                $grade = $grades->get($student->id)?->grade;

                return ($grade !== null && (float) $grade < $this->passingGrade)
                    || $recoveries->has($student->id);
            })
            ->map(function ($student) use ($grades, $recoveries) {
                $grade = $grades->get($student->id)?->grade;

                return (object) [
                    'student' => $student,
                    'current_grade' => $grade !== null ? (float) $grade : null,
                    'recoveries' => $recoveries->get($student->id, collect()),
                    'recovery_count' => $recoveries->get($student->id)?->count() ?? 0,
                ];
            })
            ->values();
    }

    public function registerRecovery(int $studentId): void
    {
        $gradeRaw = $this->recoveryGrade[$studentId] ?? null;
        $method = in_array($this->recoveryMethod[$studentId] ?? '', array_keys(ActivityRecovery::METHODS), true)
            ? $this->recoveryMethod[$studentId]
            : ActivityRecovery::METHOD_AVERAGE;

        if ($gradeRaw === null || $gradeRaw === '') {
            Flux::toast(variant: 'warning', text: __('Ingrese la nota de recuperación.'));

            return;
        }

        $activity = Activity::find($this->selectedActivityId);

        if (! $activity) {
            Flux::toast(variant: 'danger', text: __('Actividad no encontrada.'));

            return;
        }

        $current = ActivityGrade::where('activity_id', $this->selectedActivityId)
            ->where('student_id', $studentId)
            ->first();

        if (! $current || $current->grade === null) {
            Flux::toast(variant: 'warning', text: __('El estudiante no tiene una nota inicial en esta actividad.'));

            return;
        }

        $original = (float) $current->grade;
        $recovery = min(max((float) $gradeRaw, 0), (float) $activity->max_score);
        $final = ActivityRecovery::computeFinalGrade($original, $recovery, $method);

        $attempt = (int) ActivityRecovery::where('activity_id', $this->selectedActivityId)
            ->where('student_id', $studentId)
            ->withTrashed()
            ->count() + 1;

        ActivityRecovery::create([
            'activity_id' => $this->selectedActivityId,
            'student_id' => $studentId,
            'year_id' => $this->yearId,
            'recorded_by' => auth()->id(),
            'attempt_number' => $attempt,
            'original_grade' => $original,
            'recovery_grade' => $recovery,
            'update_method' => $method,
            'final_grade' => $final,
            'is_applied' => false,
        ]);

        unset($this->recoveryGrade[$studentId], $this->recoveryMethod[$studentId]);

        Flux::toast(variant: 'success', text: __('Recuperación registrada (intento #') . $attempt . ').');
    }

    public function registerExamRecovery(int $studentId): void
    {
        $gradeRaw = $this->recoveryGrade[$studentId] ?? null;
        $method = in_array($this->recoveryMethod[$studentId] ?? '', array_keys(ExamRecovery::METHODS), true)
            ? $this->recoveryMethod[$studentId]
            : ExamRecovery::METHOD_AVERAGE;

        if ($gradeRaw === null || $gradeRaw === '') {
            Flux::toast(variant: 'warning', text: __('Ingrese la nota de recuperación.'));

            return;
        }

        $current = StudentExam::where('year_id', $this->yearId)
            ->where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $this->selectedTrimesterId)
            ->where('student_id', $studentId)
            ->first();

        if (! $current || $current->grade === null) {
            Flux::toast(variant: 'warning', text: __('El estudiante no tiene nota de examen registrada.'));

            return;
        }

        $original = (float) $current->grade;
        $recovery = min(max((float) $gradeRaw, 0), $this->examMaxScore);
        $final = ExamRecovery::computeFinalGrade($original, $recovery, $method);

        $attempt = (int) ExamRecovery::where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $this->selectedTrimesterId)
            ->where('year_id', $this->yearId)
            ->where('student_id', $studentId)
            ->withTrashed()
            ->count() + 1;

        ExamRecovery::create([
            'student_id' => $studentId,
            'subject_id' => $this->selectedSubjectId,
            'grade_id' => $this->selectedGradeId,
            'trimester_id' => $this->selectedTrimesterId,
            'year_id' => $this->yearId,
            'recorded_by' => auth()->id(),
            'attempt_number' => $attempt,
            'original_grade' => $original,
            'recovery_grade' => $recovery,
            'update_method' => $method,
            'final_grade' => $final,
            'is_applied' => false,
        ]);

        unset($this->recoveryGrade[$studentId], $this->recoveryMethod[$studentId]);

        Flux::toast(variant: 'success', text: __('Recuperación de examen registrada (intento #') . $attempt . ').');
    }

    public function applyRecovery(int $recoveryId): void
    {
        $recovery = ActivityRecovery::findOrFail($recoveryId);

        if ($recovery->is_applied) {
            Flux::toast(variant: 'warning', text: __('Esta recuperación ya fue aplicada.'));

            return;
        }

        if (! $this->isGradingOpen()) {
            Flux::toast(variant: 'danger', text: __('El periodo de calificación está cerrado, no se puede actualizar el libro.'));

            return;
        }

        $recovery->update([
            'is_applied' => true,
            'applied_at' => now(),
            'recorded_by' => auth()->id(),
        ]);

        ActivityGrade::updateOrCreate(
            ['activity_id' => $recovery->activity_id, 'student_id' => $recovery->student_id],
            ['grade' => $recovery->final_grade, 'recorded_by' => auth()->id()]
        );

        HomeworkPending::where('activity_id', $recovery->activity_id)
            ->where('student_id', $recovery->student_id)
            ->where('status', 'not_submitted')
            ->whereNull('notified_at')
            ->update(['status' => 'submitted']);

        Flux::toast(variant: 'success', text: __('Nota actualizada en el libro de calificaciones.'));
    }

    public function applyExamRecovery(int $recoveryId): void
    {
        $recovery = ExamRecovery::findOrFail($recoveryId);

        if ($recovery->is_applied) {
            Flux::toast(variant: 'warning', text: __('Esta recuperación ya fue aplicada.'));

            return;
        }

        if (! $this->isGradingOpen()) {
            Flux::toast(variant: 'danger', text: __('El periodo de calificación está cerrado, no se puede actualizar el libro.'));

            return;
        }

        $recovery->update([
            'is_applied' => true,
            'applied_at' => now(),
            'recorded_by' => auth()->id(),
        ]);

        StudentExam::updateOrCreate(
            [
                'year_id' => $recovery->year_id,
                'subject_id' => $recovery->subject_id,
                'grade_id' => $recovery->grade_id,
                'trimester_id' => $recovery->trimester_id,
                'student_id' => $recovery->student_id,
            ],
            ['grade' => $recovery->final_grade, 'recorded_by' => auth()->id()]
        );

        Flux::toast(variant: 'success', text: __('Nota de examen actualizada en el libro de calificaciones.'));
    }

    public function deleteRecovery(int $recoveryId): void
    {
        $recovery = ActivityRecovery::findOrFail($recoveryId);

        if ($recovery->is_applied) {
            Flux::toast(variant: 'warning', text: __('No se puede eliminar una recuperación ya aplicada.'));

            return;
        }

        $recovery->delete();
        Flux::toast(variant: 'success', text: __('Recuperación eliminada correctamente.'));
    }

    public function deleteExamRecovery(int $recoveryId): void
    {
        $recovery = ExamRecovery::findOrFail($recoveryId);

        if ($recovery->is_applied) {
            Flux::toast(variant: 'warning', text: __('No se puede eliminar una recuperación ya aplicada.'));

            return;
        }

        $recovery->delete();
        Flux::toast(variant: 'success', text: __('Recuperación de examen eliminada correctamente.'));
    }

    public function isGradingOpen(): bool
    {
        $period = AcademicPeriod::find($this->selectedTrimesterId);

        return $period && $period->isGradingOpen();
    }

    public function getSubjectName(): string
    {
        return collect($this->subjects)->firstWhere('id', $this->selectedSubjectId)['subject_name'] ?? '';
    }

    public function getGradeName(): string
    {
        $grade = collect($this->grades)->firstWhere('id', $this->selectedGradeId);

        if (! $grade) {
            return '';
        }

        return trim(($grade['grade_name'] ?? '') . ' ' . ($grade['section'] ?? ''));
    }

    public function getBlockName(): string
    {
        return collect($this->blocks)->firstWhere('id', $this->selectedBlockId)['name'] ?? '';
    }

    public function getActivityName(): string
    {
        return collect($this->activities)->firstWhere('id', $this->selectedActivityId)['name'] ?? '';
    }

    public function getActivityMaxScore(): ?float
    {
        $activity = collect($this->activities)->firstWhere('id', $this->selectedActivityId);

        return $activity ? (float) $activity['max_score'] : null;
    }

    public function getTrimesterName(): string
    {
        return collect($this->trimesters)->firstWhere('id', $this->selectedTrimesterId)['trimester_name'] ?? '';
    }

    public function getGradeColor(?float $grade): string
    {
        if ($grade === null) {
            return 'bg-zinc-100 text-zinc-400';
        }

        if ($grade >= $this->passingGrade) {
            return 'bg-emerald-50 text-emerald-700';
        }

        return 'bg-red-50 text-red-700';
    }

    public function getMethodLabel(string $method): string
    {
        if ($this->selectedType === 'exam') {
            return ExamRecovery::METHODS[$method] ?? $method;
        }

        return ActivityRecovery::METHODS[$method] ?? $method;
    }

    public function getLowCount(): int
    {
        return $this->recoverableStudents
            ->filter(fn ($r) => $r->current_grade !== null && $r->current_grade < $this->passingGrade)
            ->count();
    }

    public function getTotalRecoveries(): int
    {
        return $this->recoverableStudents->sum('recovery_count');
    }

    public function getAppliedRecoveries(): int
    {
        return $this->recoverableStudents
            ->flatMap->recoveries
            ->filter(fn ($rec) => $rec->is_applied)
            ->count();
    }
}; ?>

<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Recuperaciones') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Seleccione la nota a recuperar y registre la recuperación por estudiante') }}</flux:text>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Recuperaciones') }}</span>
    </nav>

    @if(count($schedules) === 0)
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.academic-cap class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('No tiene asignaciones docentes') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('No se encontraron horas asignadas para su usuario en el año lectivo activo.') }}</p>
        </div>
    @else
        {{-- Main Tabs --}}
        <div class="flex gap-1 mb-6 p-1 bg-zinc-100 dark:bg-zinc-800 rounded-xl w-fit">
            <button wire:click="switchTab('register')"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition
                    {{ $activeTab === 'register' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                <flux:icon.pencil-square class="size-4 inline-block mr-1 -mt-0.5" /> {{ __('Registrar') }}
            </button>
            <button wire:click="switchTab('applied')"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition
                    {{ $activeTab === 'applied' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                <flux:icon.check-circle class="size-4 inline-block mr-1 -mt-0.5" /> {{ __('Aplicadas') }}
                @php
                    $appliedCount = count($appliedActivityRecoveries) + count($appliedExamRecoveries);
                @endphp
                @if($appliedCount > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">{{ $appliedCount }}</span>
                @endif
            </button>
        </div>

        {{-- Selectors --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 p-5 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div>
                <flux:label>{{ __('Trimestre') }}</flux:label>
                <flux:select wire:model.live="selectedTrimesterId" wire:key="trimester-select">
                    @foreach($trimesters as $trimester)
                        <option value="{{ $trimester['id'] }}" @selected((int) $selectedTrimesterId === (int) $trimester['id'])>{{ $trimester['trimester_name'] }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:label>{{ __('Curso') }}</flux:label>
                <flux:select wire:model.live="selectedGradeId" wire:key="grade-select">
                    @foreach($grades as $grade)
                        <option value="{{ $grade['id'] }}" @selected((int) $selectedGradeId === (int) $grade['id'])>{{ $grade['grade_name'] }} {{ $grade['section'] ?? '' }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:label>{{ __('Asignatura') }}</flux:label>
                <flux:select wire:model.live="selectedSubjectId" wire:key="subject-select-{{ $selectedGradeId ?? 'none' }}">
                    <option value="">{{ __('Seleccione asignatura') }}</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject['id'] }}" @selected((int) $selectedSubjectId === (int) $subject['id'])>{{ $subject['subject_name'] }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:label>{{ __('Bloque') }}</flux:label>
                <flux:select wire:model.live="selectedBlockId" wire:key="block-select-{{ $selectedSubjectId ?? 'none' }}">
                    <option value="">{{ __('Seleccione bloque') }}</option>
                    @foreach($blocks as $block)
                        <option value="{{ $block['id'] }}" @selected((int) $selectedBlockId === (int) $block['id'])>{{ $block['name'] }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        @if($activeTab === 'register')
        @if($selectedBlockId && count($activities) > 0)
            {{-- Activities --}}
            <div class="mb-4">
                <flux:label>{{ __('Elementos recuperables') }}</flux:label>
                <div class="flex flex-wrap gap-2 mt-1.5">
                    @foreach($activities as $activity)
                        <button wire:click="selectActivity({{ $activity['id'] }})"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition
                                {{ $selectedType === 'activity' && $selectedActivityId == $activity['id'] ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700 hover:border-blue-500 hover:text-blue-600' }}">
                            {{ $activity['name'] }}
                            <span class="opacity-60">/{{ $activity['max_score'] }}</span>
                        </button>
                    @endforeach
                    <button wire:click="selectExam"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition
                            {{ $selectedType === 'exam' ? 'bg-violet-600 text-white border-violet-600' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700 hover:border-violet-500 hover:text-violet-600' }}">
                        <flux:icon.academic-cap class="size-3.5 inline-block mr-0.5 -mt-0.5" /> {{ __('Examen') }}
                        <span class="opacity-60">/{{ $examMaxScore }}</span>
                    </button>
                </div>
            </div>
        @endif

        @if(($selectedType === 'activity' && $selectedActivityId) || $selectedType === 'exam')
            @php $students = $this->recoverableStudents; @endphp

            {{-- Info Header --}}
            <div class="flex items-center gap-4 mb-4 px-4 py-3 border rounded-xl flex-wrap
                {{ $selectedType === 'exam' ? 'bg-violet-50 dark:bg-violet-900/20 border-violet-200 dark:border-violet-800' : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' }}">
                <div class="flex-1 min-w-[200px]">
                    <div class="flex items-center gap-3 flex-wrap">
                        @if($selectedType === 'exam')
                            <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ __('Examen') }}</h2>
                            <flux:badge color="violet">{{ $this->getBlockName() }}</flux:badge>
                        @else
                            <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->getActivityName() }}</h2>
                            <flux:badge color="blue">{{ $this->getBlockName() }}</flux:badge>
                        @endif
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $this->getSubjectName() }} · {{ $this->getGradeName() }}</span>
                        <span class="text-sm text-zinc-500">{{ $this->getTrimesterName() }}</span>
                    </div>
                    <span class="text-xs text-zinc-500 mt-1 block">
                        {{ __('Nota mínima aprobatoria:') }} <span class="font-bold text-blue-700 dark:text-blue-300">{{ $this->passingGrade }}</span>
                        · {{ __('Nota máxima:') }} <span class="font-bold text-blue-700 dark:text-blue-300">{{ $selectedType === 'exam' ? $examMaxScore : $this->getActivityMaxScore() }}</span>
                    </span>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2.5 py-1 bg-white dark:bg-zinc-800 rounded-full text-xs font-bold text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                        {{ $this->getLowCount() }} {{ __('con baja calificación') }}
                    </span>
                    <span class="px-2.5 py-1 bg-white dark:bg-zinc-800 rounded-full text-xs font-bold text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                        {{ $this->getTotalRecoveries() }} {{ __('recuperaciones') }}
                    </span>
                    <span class="px-2.5 py-1 bg-emerald-50 dark:bg-emerald-900/20 rounded-full text-xs font-bold text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                        {{ $this->getAppliedRecoveries() }} {{ __('aplicadas') }}
                    </span>
                </div>
            </div>

            {{-- Grading closed banner --}}
            @if(! $this->isGradingOpen())
                <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                    <flux:icon.lock-closed class="size-5 text-amber-600 dark:text-amber-400 shrink-0" />
                    <div>
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">{{ __('Periodo de calificación cerrado') }}</p>
                        <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('Puede registrar recuperaciones, pero no aplicarlas al libro de calificaciones hasta reabrir el periodo.') }}</p>
                    </div>
                </div>
            @endif

            {{-- Students Table --}}
            @if(count($students) > 0)
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                                <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estudiante') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Nota actual') }}</th>
                                <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Historial de recuperación') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Nueva nota') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Método') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($students as $row)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition align-top">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <flux:avatar :name="$row->student->user?->full_name ?? ''" size="sm" />
                                            <div>
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100 text-xs">{{ $row->student->user?->full_name ?? '-' }}</div>
                                                <div class="text-[10px] font-mono text-zinc-400">{{ $row->student->student_code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold font-mono {{ $this->getGradeColor($row->current_grade) }}">
                                            {{ $row->current_grade !== null ? number_format($row->current_grade, 2) : '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($row->recoveries->isEmpty())
                                            <span class="text-xs text-zinc-400">{{ __('Sin recuperaciones') }}</span>
                                        @else
                                            <div class="space-y-1.5">
                                                @foreach($row->recoveries as $rec)
                                                    <div class="flex items-center gap-2 text-[11px] flex-wrap" wire:key="rec-{{ $rec->id }}">
                                                        <span class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-500 font-bold">#{{ $rec->attempt_number }}</span>
                                                        <span class="font-mono text-zinc-500 dark:text-zinc-400">
                                                            {{ number_format($rec->original_grade, 2) }} → {{ number_format($rec->recovery_grade, 2) }}
                                                        </span>
                                                        <span class="font-mono font-bold text-blue-700 dark:text-blue-300">
                                                            = {{ number_format($rec->final_grade, 2) }}
                                                        </span>
                                                        <span class="text-zinc-400">{{ '(' . $this->getMethodLabel($rec->update_method) . ')' }}</span>
                                                        <span class="text-[10px] text-zinc-300 dark:text-zinc-600">{{ $rec->created_at ? $rec->created_at->format('d/m') : '' }}</span>
                                                        @if($rec->is_applied)
                                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                                <flux:icon.check class="size-2.5" /> {{ __('Aplicado') }}
                                                            </span>
                                                        @else
                                                            @if($selectedType === 'exam')
                                                                <button wire:click="applyExamRecovery({{ $rec->id }})"
                                                                        @if(! $this->isGradingOpen()) disabled @endif
                                                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold transition
                                                                               {{ $this->isGradingOpen() ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 cursor-not-allowed' }}"
                                                                        title="{{ __('Actualizar en el libro de calificaciones') }}">
                                                                    <flux:icon.pencil-square class="size-2.5" /> {{ __('Aplicar') }}
                                                                </button>
                                                                <button wire:click="deleteExamRecovery({{ $rec->id }})"
                                                                        wire:confirm="{{ __('Eliminar esta recuperación?') }}"
                                                                        class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 transition">
                                                                    <flux:icon.trash class="size-2.5" />
                                                                </button>
                                                            @else
                                                                <button wire:click="applyRecovery({{ $rec->id }})"
                                                                        @if(! $this->isGradingOpen()) disabled @endif
                                                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold transition
                                                                               {{ $this->isGradingOpen() ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 cursor-not-allowed' }}"
                                                                        title="{{ __('Actualizar en el libro de calificaciones') }}">
                                                                    <flux:icon.pencil-square class="size-2.5" /> {{ __('Aplicar') }}
                                                                </button>
                                                                <button wire:click="deleteRecovery({{ $rec->id }})"
                                                                        wire:confirm="{{ __('Eliminar esta recuperación?') }}"
                                                                        class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 transition">
                                                                    <flux:icon.trash class="size-2.5" />
                                                                </button>
                                                            @endif
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="number" min="0" max="{{ $this->getActivityMaxScore() }}" step="0.1"
                                               wire:model="recoveryGrade.{{ $row->student->id }}"
                                               class="w-16 px-1.5 py-1 border border-zinc-200 dark:border-zinc-600 rounded text-xs font-mono text-center bg-white dark:bg-zinc-800 focus:outline-2 focus:outline-blue-500"
                                               placeholder="—">
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <select wire:model="recoveryMethod.{{ $row->student->id }}"
                                                class="w-full min-w-[120px] px-2 py-1 border border-zinc-200 dark:border-zinc-600 rounded text-xs bg-white dark:bg-zinc-800 focus:outline-2 focus:outline-blue-500">
                                            @php
                                                $methods = $selectedType === 'exam'
                                                    ? \App\Models\Academic\GradeBook\Summaries\Supplementary\ExamRecovery::METHODS
                                                    : \App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery::METHODS;
                                            @endphp
                                            @foreach($methods as $value => $label)
                                                <option value="{{ $value }}" @selected(($recoveryMethod[$row->student->id] ?? 'average') === $value)>{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <flux:button size="sm" variant="primary"
                                            wire:click="{{ $selectedType === 'exam' ? 'registerExamRecovery' : 'registerRecovery' }}({{ $row->student->id }})">
                                            <flux:icon.arrow-path class="size-3.5" /> {{ __('Registrar') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Method Legend --}}
                <div class="flex flex-wrap gap-4 mt-4 px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 self-center">{{ __('Método de actualización:') }}</span>
                    <span class="inline-flex items-center gap-1.5 text-xs">
                        <flux:icon.plus class="size-3 text-blue-500" />
                        {{ __('Promedio') }} = (nota baja + nota de recuperación) / 2
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-xs">
                        <flux:icon.arrow-trending-up class="size-3 text-emerald-500" />
                        {{ __('La más alta') }} = se toma la mejor de las dos notas
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-xs text-zinc-400">
                        {{ __('Al aplicar se reemplaza la nota baja por la nueva en el libro de calificaciones.') }}
                    </span>
                </div>
            @else
                <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700 border-dashed">
                    <flux:icon.check-badge class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-base font-semibold">{{ __('Sin estudiantes por recuperar') }}</p>
                    <p class="text-sm text-zinc-400 mt-1">{{ __('No hay estudiantes con calificación menor a ') }}{{ $this->passingGrade }}{{ __(' en esta actividad.') }}</p>
                </div>
            @endif
        @elseif($selectedBlockId)
            <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:icon.document-text class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
                <p class="text-base font-semibold">{{ __('Seleccione una actividad') }}</p>
                <p class="text-sm text-zinc-400 mt-1">{{ __('Elija una de las actividades del bloque para ver los estudiantes con baja calificación.') }}</p>
            </div>
        @elseif(count($blocks) === 0)
            <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:icon.archive-box class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
                <p class="text-base font-semibold">{{ __('No hay bloques para la selección') }}</p>
                <p class="text-sm text-zinc-400 mt-1">{{ __('Cree bloques y actividades desde el Libro de Calificaciones para poder registrar recuperaciones.') }}</p>
            </div>
        @else
            <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:icon.book-open class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
                <p class="text-base font-semibold">{{ __('Seleccione un bloque y una actividad') }}</p>
                <p class="text-sm text-zinc-400 mt-1">{{ __('Complete los filtros para comenzar.') }}</p>
            </div>
        @endif
        @elseif($activeTab === 'applied')
            {{-- Applied Recoveries --}}
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ __('Recuperaciones Aplicadas') }}</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Historial de recuperaciones ya aplicadas al libro de calificaciones') }}</p>
                    </div>
                    <div>
                        <flux:label>{{ __('Trimestre') }}</flux:label>
                        <flux:select wire:model.live="appliedTrimesterId" wire:key="applied-trimester-select">
                            @foreach($trimesters as $trimester)
                                <option value="{{ $trimester['id'] }}" @selected((int) $appliedTrimesterId === (int) $trimester['id'])>{{ $trimester['trimester_name'] }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                @php
                    $totalApplied = count($appliedActivityRecoveries) + count($appliedExamRecoveries);
                @endphp

                @if($totalApplied > 0)
                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estudiante') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Tipo') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Actividad / Examen') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Original') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Recuperación') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Final') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Método') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Fecha aplicación') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($appliedActivityRecoveries as $rec)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <flux:avatar :name="$rec['student']['user']['full_name'] ?? ''" size="sm" />
                                                <div>
                                                    <div class="font-medium text-zinc-900 dark:text-zinc-100 text-xs">{{ $rec['student']['user']['full_name'] ?? '-' }}</div>
                                                    <div class="text-[10px] font-mono text-zinc-400">{{ $rec['student']['student_code'] ?? '' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:badge color="blue">{{ __('Actividad') }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-zinc-700 dark:text-zinc-300">{{ $rec['activity']['name'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="font-mono text-xs font-bold {{ $this->getGradeColor($rec['original_grade']) }}">{{ number_format($rec['original_grade'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="font-mono text-xs font-bold text-zinc-700 dark:text-zinc-300">{{ number_format($rec['recovery_grade'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ number_format($rec['final_grade'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center text-xs text-zinc-500">{{ $this->getMethodLabel($rec['update_method']) }}</td>
                                        <td class="px-4 py-3 text-center text-xs text-zinc-500">{{ $rec['applied_at'] ? \Carbon\Carbon::parse($rec['applied_at'])->format('d/m/Y H:i') : '—' }}</td>
                                    </tr>
                                @endforeach
                                @foreach($appliedExamRecoveries as $rec)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <flux:avatar :name="$rec['student']['user']['full_name'] ?? ''" size="sm" />
                                                <div>
                                                    <div class="font-medium text-zinc-900 dark:text-zinc-100 text-xs">{{ $rec['student']['user']['full_name'] ?? '-' }}</div>
                                                    <div class="text-[10px] font-mono text-zinc-400">{{ $rec['student']['student_code'] ?? '' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:badge color="violet">{{ __('Examen') }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-zinc-700 dark:text-zinc-300">{{ __('Examen') }} — {{ $this->getSubjectName() }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="font-mono text-xs font-bold {{ $this->getGradeColor($rec['original_grade']) }}">{{ number_format($rec['original_grade'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="font-mono text-xs font-bold text-zinc-700 dark:text-zinc-300">{{ number_format($rec['recovery_grade'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ number_format($rec['final_grade'], 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center text-xs text-zinc-500">{{ $this->getMethodLabel($rec['update_method']) }}</td>
                                        <td class="px-4 py-3 text-center text-xs text-zinc-500">{{ $rec['applied_at'] ? \Carbon\Carbon::parse($rec['applied_at'])->format('d/m/Y H:i') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap gap-4 mt-4 px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 self-center">{{ __('Resumen') }}:</span>
                        <span class="inline-flex items-center gap-1.5 text-xs">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            {{ count($appliedActivityRecoveries) }} {{ __('de actividad') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-xs">
                            <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                            {{ count($appliedExamRecoveries) }} {{ __('de examen') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold">
                            {{ $totalApplied }} {{ __('total aplicadas') }}
                        </span>
                    </div>
                @else
                    <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700 border-dashed">
                        <flux:icon.check-badge class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-base font-semibold">{{ __('Sin recuperaciones aplicadas') }}</p>
                        <p class="text-sm text-zinc-400 mt-1">{{ __('No hay recuperaciones aplicadas en este trimestre.') }}</p>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
