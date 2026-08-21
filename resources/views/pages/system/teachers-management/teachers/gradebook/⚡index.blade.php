<?php

declare(strict_types=1);

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Academic\GradeBook\Summaries\Supplementary\SupplementaryExam;
use App\Models\Academic\GradeBook\Cualitatives\CareerGuidance\CareerGuidance;
use App\Models\Academic\GradeBook\Cualitatives\CareerGuidance\CareerGuidanceIndicator;
use App\Models\Academic\GradeBook\Cualitatives\ClassroomSupport\IntegralClassroomSupport;
use App\Models\Academic\GradeBook\Cualitatives\ClassroomSupport\IntegralClassroomSupportIndicator;
use App\Models\Academic\GradeBook\Cualitatives\ReadingPromotion\ReadingPromotion;
use App\Models\Academic\GradeBook\Cualitatives\ReadingPromotion\ReadingPromotionIndicator;
use App\Models\Identity\Users\Student;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\GradingScheme;
use App\Models\StudentManagement\Academics\HomeworkPending;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Libro de Calificaciones')] class extends Component {
    use WithPagination;

    const SUPLETORIO_MODE = -1;

    public ?int $yearId = null;
    public ?int $selectedSubjectId = null;
    public ?int $selectedGradeId = null;
    public mixed $selectedTrimesterId = null;
    public array $schedules = [];
    public array $subjects = [];
    public array $grades = [];
    public array $trimesters = [];
    public ?GradingScheme $gradingScheme = null;
    public $assessmentBlocks;
    public array $exams = [];
    public array $projects = [];
    public mixed $activeBlockId = null;
    public string $activeTab = '';
    public array $supletorios = [];

    public bool $showBlockModal = false;
    public ?int $editingBlockId = null;
    public array $blockForm = ['name' => '', 'description' => '', 'internal_percentage' => null, 'order' => 0];

    public bool $showActivityModal = false;
    public ?int $activityBlockId = null;
    public ?int $editingActivityId = null;
    public array $activityForm = ['name' => '', 'topic' => '', 'description' => '', 'date' => null, 'max_score' => 10];

    public bool $showInlineForm = false;

    public array $qualitativeIndicators = [];
    public array $qualitativeGrades = [];
    public string $qualitativeType = '';

    private const QUAL_VALUE_MAP = ['S' => 4, 'F' => 3, 'O' => 2, 'N' => 1];
    private const QUAL_LETTER_TABLE = [
        ['min' => 35, 'max' => 36, 'letter' => 'A+'], ['min' => 33, 'max' => 34, 'letter' => 'A-'],
        ['min' => 30, 'max' => 32, 'letter' => 'B+'], ['min' => 27, 'max' => 29, 'letter' => 'B-'],
        ['min' => 20, 'max' => 26, 'letter' => 'C+'], ['min' => 18, 'max' => 19, 'letter' => 'C-'],
        ['min' => 15, 'max' => 17, 'letter' => 'D+'], ['min' => 13, 'max' => 14, 'letter' => 'D-'],
        ['min' => 11, 'max' => 12, 'letter' => 'E+'], ['min' => 0, 'max' => 10, 'letter' => 'E-'],
    ];
    private const READING_LETTER_TABLE = [
        ['min' => 9.01, 'max' => 10, 'letter' => 'A+'], ['min' => 8.01, 'max' => 9, 'letter' => 'A-'],
        ['min' => 7.01, 'max' => 8, 'letter' => 'B+'], ['min' => 6.01, 'max' => 7, 'letter' => 'B-'],
        ['min' => 5.01, 'max' => 6, 'letter' => 'C+'], ['min' => 4.01, 'max' => 5, 'letter' => 'C-'],
        ['min' => 3.01, 'max' => 4, 'letter' => 'D+'], ['min' => 2.01, 'max' => 3, 'letter' => 'D-'],
        ['min' => 1.01, 'max' => 2, 'letter' => 'E+'], ['min' => 0, 'max' => 1, 'letter' => 'E-'],
    ];

    public function mount(): void
    {
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        $this->loadTeacherData();
    }

    protected function loadTeacherData(): void
    {
        $teacherId = auth()->user()->teacher?->id;

        $this->schedules = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $this->yearId)
            ->with('grade.nivel.shift', 'subject')
            ->get()
            ->toArray();

        $this->subjects = collect($this->schedules)->pluck('subject')->unique('id')->values()->all();
        $this->grades = collect($this->schedules)->pluck('grade')->unique('id')->values()->all();

        $currentYear = app(AcademicYearService::class)->getActiveYear();
        if ($currentYear) {
            $this->trimesters = $currentYear->academicPeriods()
                ->where('status', 1)->where('is_supletorio', false)
                ->orderBy('id')->get()->toArray();

            $this->gradingScheme = GradingScheme::where('year_id', $currentYear->id)
                ->where('status', 1)->first();
        }

        if (count($this->trimesters) > 0 && !$this->selectedTrimesterId) {
            $this->selectedTrimesterId = $this->trimesters[0]['id'];
        }
        if (!$this->selectedSubjectId && count($this->subjects) > 0) {
            $this->selectedSubjectId = $this->subjects[0]['id'];
            $this->loadGrades();
        }
        if (!$this->selectedGradeId && count($this->grades) > 0) {
            $this->selectedGradeId = $this->grades[0]['id'];
        }
        if ($this->selectedSubjectId && $this->selectedGradeId) {
            $this->loadData();
        }
    }

    public function updatedSelectedSubjectId(): void
    {
        $this->selectedGradeId = null;
        $this->showBlockModal = false;
        $this->showActivityModal = false;
        $this->activeBlockId = null;
        $this->activeTab = '';
        $this->loadGrades();
        if (count($this->grades) === 1) {
            $this->selectedGradeId = $this->grades[0]['id'];
        }
        $this->loadData();
    }

    protected function loadGrades(): void
    {
        $query = ClassSchedule::where('teacher_id', auth()->user()->teacher?->id)
            ->where('year_id', $this->yearId);
        if ($this->selectedSubjectId) {
            $query->where('subject_id', $this->selectedSubjectId);
        }
        $this->grades = $query->with('grade')->get()->pluck('grade')->unique('id')->values()->all();
    }

    public function updatedSelectedGradeId(): void
    {
        $this->showBlockModal = false;
        $this->showActivityModal = false;
        $this->activeBlockId = null;
        $this->activeTab = '';
        $this->loadData();
    }

    public function updatedSelectedTrimesterId(): void
    {
        $this->activeBlockId = $this->selectedTrimesterId == self::SUPLETORIO_MODE ? '_supletorios' : null;
        $this->activeTab = $this->selectedTrimesterId == self::SUPLETORIO_MODE ? 'supletorios' : '';
        $this->loadData();
    }

    public function loadData(): void
    {
        if ($this->isQualitativeSubject()) {
            $this->assessmentBlocks = collect();
            $this->exams = [];
            $this->projects = [];
            $this->supletorios = [];
            $this->loadQualitativeData();
            $this->resetPage();
            return;
        }
        if ($this->selectedTrimesterId == self::SUPLETORIO_MODE) {
            $this->assessmentBlocks = collect();
            $this->exams = [];
            $this->projects = [];
            $this->activeBlockId = '_supletorios';
        } else {
            $this->loadAssessmentBlocks();
            $this->loadExamAndProject();
        }
        $this->loadSupletorios();
        $this->resetPage();
    }

    protected function getStudentIds(): array
    {
        if (!$this->selectedGradeId) {
            return [];
        }
        return \App\Models\Management\Enrollments\StudentEnrollment::where('grade_id', $this->selectedGradeId)
            ->where('year_id', $this->yearId)
            ->pluck('student_id')
            ->toArray();
    }

    protected function loadAssessmentBlocks(): void
    {
        if (!$this->selectedSubjectId || !$this->selectedGradeId || !$this->selectedTrimesterId) {
            $this->assessmentBlocks = collect();
            return;
        }
        $studentIds = $this->getStudentIds();
        $this->assessmentBlocks = AssessmentBlock::where('year_id', $this->yearId)
            ->where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $this->selectedTrimesterId)
            ->where('teacher_id', auth()->user()->teacher?->id)
            ->with(['activities.grades' => fn ($q) => $q->whereIn('student_id', $studentIds)])
            ->orderBy('order')->orderBy('created_at')->get();

        if ($this->assessmentBlocks->count() > 0) {
            $exists = $this->assessmentBlocks->firstWhere('id', $this->activeBlockId);
            if (!$exists && $this->activeBlockId !== '_supletorios') {
                $this->activeBlockId = $this->assessmentBlocks->first()->id;
            }
        } elseif ($this->activeBlockId !== '_supletorios') {
            $this->activeBlockId = null;
        }
    }

    protected function loadExamAndProject(): void
    {
        if (!$this->selectedSubjectId || !$this->selectedGradeId || !$this->selectedTrimesterId) {
            $this->exams = [];
            $this->projects = [];
            return;
        }
        $studentIds = $this->getStudentIds();
        $this->exams = StudentExam::where('year_id', $this->yearId)
            ->where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $this->selectedTrimesterId)
            ->whereIn('student_id', $studentIds)
            ->get()->keyBy('student_id')->toArray();

        $this->projects = StudentProject::where('year_id', $this->yearId)
            ->where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $this->selectedTrimesterId)
            ->whereIn('student_id', $studentIds)
            ->get()->keyBy('student_id')->toArray();
    }

    protected function loadSupletorios(): void
    {
        if (!$this->selectedSubjectId || !$this->selectedGradeId) {
            $this->supletorios = [];
            return;
        }
        $studentIds = $this->getStudentIds();
        $this->supletorios = SupplementaryExam::where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('year_id', $this->yearId)
            ->whereIn('student_id', $studentIds)
            ->get()->keyBy('student_id')->toArray();
    }

    public function selectBlock(mixed $blockId): void
    {
        $this->activeBlockId = $blockId;
        $this->activeTab = 'block_' . $blockId;
    }

    public function toggleInlineForm(): void
    {
        $this->showInlineForm = ! $this->showInlineForm;
        if (!$this->showInlineForm) {
            $this->resetActivityForm();
        }
    }

    public function getStudents()
    {
        if (!$this->selectedGradeId || !$this->selectedSubjectId) {
            return collect();
        }
        return Student::whereHas('enrollments', fn ($q) => $q->where('grade_id', $this->selectedGradeId)->where('year_id', $this->yearId))
            ->with('user')
            ->orderBy(\App\Models\User::select('lastname')->whereColumn('users.id', 'students.user_id'))
            ->paginate(50);
    }

    public function getStudentBlockAverage($studentId, $blockId)
    {
        $block = $this->assessmentBlocks->firstWhere('id', $blockId);
        if (!$block || $block->activities->count() === 0) {
            return null;
        }
        $total = 0;
        foreach ($block->activities as $activity) {
            $grade = $activity->grades->firstWhere('student_id', $studentId);
            if ($grade && $grade->grade !== null) {
                $total += $grade->grade;
            }
        }
        return floor($total / $block->activities->count() * 100) / 100;
    }

    public function getStudentFormativeAverage($studentId)
    {
        $blockAverages = [];
        foreach ($this->assessmentBlocks as $block) {
            $avg = $this->getStudentBlockAverage($studentId, $block->id);
            if ($avg !== null) {
                $blockAverages[] = $avg;
            }
        }
        if (count($blockAverages) === 0) {
            return null;
        }
        return floor(array_sum($blockAverages) / count($blockAverages) * 100) / 100;
    }

    public function getStudentTotalAverage($studentId)
    {
        if (!$this->gradingScheme) {
            return null;
        }
        $formativeAvg = $this->getStudentFormativeAverage($studentId);
        $examGrade = $this->exams[$studentId]['grade'] ?? null;
        $projectGrade = $this->projects[$studentId]['grade'] ?? null;
        if ($formativeAvg === null && $examGrade === null && $projectGrade === null) {
            return null;
        }
        $formativeWeighted = $formativeAvg !== null ? $formativeAvg * ($this->gradingScheme->formative_percentage / 100) : 0;
        $examWeighted = $examGrade !== null ? $examGrade * ($this->gradingScheme->exam_percentage / 100) : 0;
        $projectWeighted = $projectGrade !== null ? $projectGrade * ($this->gradingScheme->project_percentage / 100) : 0;
        return floor(($formativeWeighted + $examWeighted + $projectWeighted) * 100) / 100;
    }

    public function getTrimesterFormativeAverage($studentId, $trimesterId): ?float
    {
        if (!$this->selectedSubjectId || !$this->selectedGradeId) {
            return null;
        }
        $blocks = AssessmentBlock::where('year_id', $this->yearId)
            ->where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $trimesterId)
            ->where('teacher_id', auth()->user()->teacher?->id)
            ->with(['activities.grades' => fn ($q) => $q->where('student_id', $studentId)])
            ->orderBy('order')->get();

        $blockAverages = [];
        foreach ($blocks as $block) {
            if ($block->activities->count() === 0) {
                continue;
            }
            $total = 0;
            foreach ($block->activities as $activity) {
                $grade = $activity->grades->firstWhere('student_id', $studentId);
                if ($grade && $grade->grade !== null) {
                    $total += $grade->grade;
                }
            }
            $blockAverages[] = $total / $block->activities->count();
        }
        if (count($blockAverages) === 0) {
            return null;
        }
        return floor(array_sum($blockAverages) / count($blockAverages) * 100) / 100;
    }

    public function getPerformanceColor($average): string
    {
        if ($average === null) {
            return 'text-zinc-400';
        }
        if ($average >= 7) {
            return 'text-emerald-600';
        }
        if ($average >= 5) {
            return 'text-amber-600';
        }
        return 'text-red-600';
    }

    public function getPerformanceBgColor($average): string
    {
        if ($average === null) {
            return 'bg-zinc-50';
        }
        if ($average >= 7) {
            return 'bg-emerald-50 border-emerald-200';
        }
        if ($average >= 5) {
            return 'bg-amber-50 border-amber-200';
        }
        return 'bg-red-50 border-red-200';
    }

    public function getTrimesterTotal($studentId, $trimesterId)
    {
        if (!$this->selectedSubjectId || !$this->selectedGradeId || !$this->gradingScheme) {
            return null;
        }
        $blocks = AssessmentBlock::where('year_id', $this->yearId)
            ->where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $trimesterId)
            ->where('teacher_id', auth()->user()->teacher?->id)
            ->with(['activities.grades' => fn ($q) => $q->where('student_id', $studentId)])
            ->orderBy('order')->get();

        $blockAverages = [];
        foreach ($blocks as $block) {
            if ($block->activities->count() === 0) {
                continue;
            }
            $total = 0;
            foreach ($block->activities as $activity) {
                $grade = $activity->grades->firstWhere('student_id', $studentId);
                if ($grade && $grade->grade !== null) {
                    $total += $grade->grade;
                }
            }
            $blockAverages[] = $total / $block->activities->count();
        }

        $formativeAvg = count($blockAverages) > 0 ? array_sum($blockAverages) / count($blockAverages) : null;

        $exam = StudentExam::where('year_id', $this->yearId)
            ->where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $trimesterId)
            ->where('student_id', $studentId)->first();

        $project = StudentProject::where('year_id', $this->yearId)
            ->where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $trimesterId)
            ->where('student_id', $studentId)->first();

        $formativeWeighted = $formativeAvg !== null ? $formativeAvg * ($this->gradingScheme->formative_percentage / 100) : 0;
        $examWeighted = $exam && $exam->grade !== null ? $exam->grade * ($this->gradingScheme->exam_percentage / 100) : 0;
        $projectWeighted = $project && $project->grade !== null ? $project->grade * ($this->gradingScheme->project_percentage / 100) : 0;

        $total = $formativeWeighted + $examWeighted + $projectWeighted;

        if ($total == 0 && !$formativeAvg && !$exam && !$project) {
            return null;
        }
        return round($total, 2);
    }

    public function getAnnualAverage($studentId)
    {
        if (!$this->selectedSubjectId || !$this->selectedGradeId || !$this->gradingScheme) {
            return null;
        }
        $trimesters = collect($this->trimesters);
        $totalSum = 0;
        $activeTrimesterCount = 0;
        $hasAnyData = false;

        foreach ($trimesters as $trimester) {
            $hasBlocks = AssessmentBlock::where('year_id', $this->yearId)
                ->where('subject_id', $this->selectedSubjectId)
                ->where('grade_id', $this->selectedGradeId)
                ->where('trimester_id', $trimester['id'])
                ->where('teacher_id', auth()->user()->teacher?->id)->exists();

            $hasExams = StudentExam::where('year_id', $this->yearId)
                ->where('subject_id', $this->selectedSubjectId)
                ->where('grade_id', $this->selectedGradeId)
                ->where('trimester_id', $trimester['id'])->exists();

            $hasProjects = StudentProject::where('year_id', $this->yearId)
                ->where('subject_id', $this->selectedSubjectId)
                ->where('grade_id', $this->selectedGradeId)
                ->where('trimester_id', $trimester['id'])->exists();

            if (!$hasBlocks && !$hasExams && !$hasProjects) {
                continue;
            }
            $activeTrimesterCount++;
            $total = $this->getTrimesterTotal($studentId, $trimester['id']);
            if ($total !== null) {
                $totalSum += $total;
                $hasAnyData = true;
            }
        }
        if (!$hasAnyData || $activeTrimesterCount === 0) {
            return null;
        }
        return round($totalSum / $activeTrimesterCount, 2);
    }

    public function openCreateBlock(): void
    {
        $this->resetBlockForm();
        $this->editingBlockId = null;
        $this->showBlockModal = true;
    }

    public function openEditBlock($blockId): void
    {
        $block = AssessmentBlock::findOrFail($blockId);
        $this->editingBlockId = $blockId;
        $this->blockForm = [
            'name' => $block->name, 'description' => $block->description,
            'internal_percentage' => $block->internal_percentage, 'order' => $block->order,
        ];
        $this->showBlockModal = true;
    }

    public function saveBlock(): void
    {
        if (!$this->isGradingOpen()) {
            Flux::toast(variant: 'danger', text: __('No se pueden crear o editar bloques. El periodo de calificacion esta cerrado.'));
            return;
        }
        $this->validate([
            'blockForm.name' => 'required|string|max:255',
            'blockForm.description' => 'nullable|string|max:1000',
            'blockForm.internal_percentage' => 'nullable|numeric|min:0|max:100',
            'blockForm.order' => 'nullable|integer|min:0',
        ]);
        $data = [
            'year_id' => $this->yearId, 'subject_id' => $this->selectedSubjectId,
            'grade_id' => $this->selectedGradeId, 'trimester_id' => $this->selectedTrimesterId,
            'teacher_id' => auth()->user()->teacher?->id,
            'name' => $this->blockForm['name'], 'description' => $this->blockForm['description'],
            'internal_percentage' => $this->blockForm['internal_percentage'],
            'order' => $this->blockForm['order'] ?? 0,
        ];
        try {
            if ($this->editingBlockId) {
                AssessmentBlock::findOrFail($this->editingBlockId)->update($data);
                Flux::toast(variant: 'success', text: __('Bloque actualizado correctamente.'));
            } else {
                AssessmentBlock::create($data);
                Flux::toast(variant: 'success', text: __('Bloque creado correctamente.'));
            }
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: __('Error al guardar el bloque: ') . $e->getMessage());
            return;
        }
        $this->showBlockModal = false;
        $this->resetBlockForm();
        $this->loadAssessmentBlocks();
    }

    public function deleteBlock($blockId): void
    {
        if (!$this->isGradingOpen()) {
            Flux::toast(variant: 'danger', text: __('No se pueden eliminar bloques. El periodo de calificacion esta cerrado.'));
            return;
        }
        AssessmentBlock::findOrFail($blockId)->delete();
        $this->loadAssessmentBlocks();
        Flux::toast(variant: 'success', text: __('Bloque eliminado correctamente.'));
    }

    protected function resetBlockForm(): void
    {
        $this->blockForm = ['name' => '', 'description' => '', 'internal_percentage' => null, 'order' => 0];
        $this->resetValidation();
    }

    public function openCreateActivity($blockId): void
    {
        $this->resetActivityForm();
        $this->activityBlockId = $blockId;
        $this->editingActivityId = null;
        $this->showActivityModal = true;
    }

    public function openEditActivity($blockId, $activityId): void
    {
        $activity = Activity::findOrFail($activityId);
        $this->activityBlockId = $blockId;
        $this->editingActivityId = $activityId;
        $this->activityForm = [
            'name' => $activity->name, 'topic' => $activity->topic,
            'description' => $activity->description,
            'date' => $activity->date?->format('Y-m-d'), 'max_score' => $activity->max_score,
        ];
        $this->showActivityModal = true;
    }

    public function saveActivity(): void
    {
        if (!$this->isGradingOpen()) {
            Flux::toast(variant: 'danger', text: __('No se pueden crear o editar actividades. El periodo de calificacion esta cerrado.'));
            return;
        }
        $this->validate([
            'activityForm.name' => 'required|string|max:255',
            'activityForm.topic' => 'required|string|max:255',
            'activityForm.description' => 'nullable|string|max:1000',
            'activityForm.date' => [
                'required', 'date', 'after_or_equal:today',
                function ($attribute, $value, $fail) {
                    $date = \Carbon\Carbon::parse($value);
                    if ($date->isWeekend()) {
                        $fail('La fecha debe ser un dia laborable (lunes a viernes).');
                    }
                },
            ],
            'activityForm.max_score' => 'required|numeric|min:0.01|max:999.99',
        ]);
        $data = [
            'assessment_block_id' => $this->activityBlockId,
            'name' => $this->activityForm['name'],
            'topic' => $this->activityForm['topic'],
            'description' => $this->activityForm['description'],
            'date' => $this->activityForm['date'],
            'max_score' => $this->activityForm['max_score'],
        ];
        try {
            if ($this->editingActivityId) {
                Activity::findOrFail($this->editingActivityId)->update($data);
                Flux::toast(variant: 'success', text: __('Actividad actualizada correctamente.'));
            } else {
                Activity::create($data);
                Flux::toast(variant: 'success', text: __('Actividad creada correctamente.'));
            }
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: __('Error al guardar la actividad: ') . $e->getMessage());
            return;
        }
        $this->showActivityModal = false;
        $this->resetActivityForm();
        $this->loadAssessmentBlocks();
    }

    public function deleteActivity($activityId): void
    {
        if (!$this->isGradingOpen()) {
            Flux::toast(variant: 'danger', text: __('No se pueden eliminar actividades. El periodo de calificacion esta cerrado.'));
            return;
        }
        Activity::findOrFail($activityId)->delete();
        $this->loadAssessmentBlocks();
        Flux::toast(variant: 'success', text: __('Actividad eliminada correctamente.'));
    }

    protected function resetActivityForm(): void
    {
        $this->activityForm = ['name' => '', 'topic' => '', 'description' => '', 'date' => null, 'max_score' => 10];
        $this->resetValidation();
    }

    public function quickAddActivity($blockId): void
    {
        $this->activityBlockId = $blockId;
        $this->saveActivity();
    }

    public function saveGrade($activityId, $studentId, $value): void
    {
        if (!$this->isGradingOpen()) {
            Flux::toast(variant: 'danger', text: __('No se pueden guardar calificaciones. El periodo de calificacion esta cerrado.'));
            return;
        }
        $grade = $value !== '' ? $value : null;
        if ($grade !== null) {
            $grade = min(max((float) $grade, 0), 10);
        }
        ActivityGrade::updateOrCreate(
            ['activity_id' => $activityId, 'student_id' => $studentId],
            ['grade' => $grade, 'recorded_by' => auth()->id()]
        );

        $activity = Activity::with('assessmentBlock')->find($activityId);
        if ($activity && $activity->assessmentBlock) {
            $block = $activity->assessmentBlock;
            $enrolledIds = \App\Models\Management\Enrollments\StudentEnrollment::where('grade_id', $block->grade_id)
                ->where('year_id', $block->year_id)->pluck('student_id')->toArray();
            $allGrades = ActivityGrade::where('activity_id', $activityId)
                ->whereIn('student_id', $enrolledIds)->get()->keyBy('student_id');

            foreach ($enrolledIds as $sid) {
                $existing = $allGrades->get($sid);
                if (!$existing || $existing->grade === null) {
                    HomeworkPending::updateOrCreate(
                        ['activity_id' => $activityId, 'student_id' => $sid],
                        ['subject_id' => $block->subject_id, 'grade_id' => $block->grade_id,
                         'teacher_id' => auth()->user()->teacher?->id, 'year_id' => $block->year_id,
                         'trimester_id' => $block->trimester_id,
                         'description' => 'Tarea no presentada: ' . $activity->name,
                         'due_date' => $activity->date ?? now(), 'status' => 'not_submitted']
                    );
                } else {
                    HomeworkPending::where('activity_id', $activityId)
                        ->where('student_id', $sid)->where('status', 'not_submitted')
                        ->whereNull('notified_at')->update(['status' => 'submitted']);
                }
            }
        }
        $this->loadAssessmentBlocks();
    }

    public function saveExamGrade($studentId, $value): void
    {
        if (!$this->isGradingOpen()) {
            Flux::toast(variant: 'danger', text: __('No se pueden guardar calificaciones. El periodo de calificacion esta cerrado.'));
            return;
        }
        $grade = $value !== '' ? $value : null;
        if ($grade !== null) {
            $grade = min(max((float) $grade, 0), 10);
        }
        StudentExam::updateOrCreate(
            ['subject_id' => $this->selectedSubjectId, 'grade_id' => $this->selectedGradeId,
             'trimester_id' => $this->selectedTrimesterId, 'student_id' => $studentId, 'year_id' => $this->yearId],
            ['grade' => $grade, 'recorded_by' => auth()->id()]
        );
        $this->loadExamAndProject();
    }

    public function saveProjectGrade($studentId, $value): void
    {
        if (!$this->isGradingOpen()) {
            Flux::toast(variant: 'danger', text: __('No se pueden guardar calificaciones. El periodo de calificacion esta cerrado.'));
            return;
        }
        $grade = $value !== '' ? $value : null;
        if ($grade !== null) {
            $grade = min(max((float) $grade, 0), 10);
        }
        StudentProject::updateOrCreate(
            ['subject_id' => $this->selectedSubjectId, 'grade_id' => $this->selectedGradeId,
             'trimester_id' => $this->selectedTrimesterId, 'student_id' => $studentId, 'year_id' => $this->yearId],
            ['grade' => $grade, 'recorded_by' => auth()->id()]
        );
        $this->loadExamAndProject();
    }

    public function saveSupletorioGrade($studentId, $value): void
    {
        if (!$this->isSupletorioAvailable()) {
            Flux::toast(variant: 'danger', text: __('No se pueden guardar calificaciones de supletorio. Todavia no estan disponibles.'));
            return;
        }
        if ($value === '' || $value === null) {
            SupplementaryExam::where(['student_id' => $studentId, 'subject_id' => $this->selectedSubjectId,
                'grade_id' => $this->selectedGradeId, 'year_id' => $this->yearId])->delete();
        } else {
            $grade = min(max((float) $value, 0), 10);
            SupplementaryExam::updateOrCreate(
                ['student_id' => $studentId, 'subject_id' => $this->selectedSubjectId,
                 'grade_id' => $this->selectedGradeId, 'year_id' => $this->yearId],
                ['grade' => $grade, 'recorded_by' => auth()->id()]
            );
        }
        $this->loadSupletorios();
    }

    public function isQualitativeSubject(?int $subjectId = null): bool
    {
        $id = $subjectId ?? $this->selectedSubjectId;
        if (!$id) {
            return false;
        }
        return $this->getQualitativeType($id) !== '';
    }

    public function getQualitativeType(?int $subjectId = null): string
    {
        $id = $subjectId ?? $this->selectedSubjectId;
        if (!$id) {
            return '';
        }
        $subject = Subject::find($id);
        if (!$subject) {
            return '';
        }
        $name = strtolower(\Illuminate\Support\Str::ascii($subject->subject_name));
        if (str_contains($name, 'orientacion vocacional') || str_contains($name, 'ovp')) {
            return 'career_guidance';
        }
        if (str_contains($name, 'acompanamiento integral') || str_contains($name, 'aiac') || str_contains($name, 'civica')) {
            return 'classroom_support';
        }
        if (str_contains($name, 'animacion a la lectura')) {
            return 'reading_promotion';
        }
        return '';
    }

    public function loadQualitativeData(): void
    {
        if (!$this->isQualitativeSubject() || !$this->selectedGradeId || !$this->selectedTrimesterId || $this->selectedTrimesterId == self::SUPLETORIO_MODE) {
            $this->qualitativeIndicators = [];
            $this->qualitativeGrades = [];
            $this->qualitativeType = '';
            return;
        }
        $this->qualitativeType = $this->getQualitativeType();
        $yearId = $this->yearId;
        $subjectId = $this->selectedSubjectId;
        $gradeId = $this->selectedGradeId;
        $trimesterId = $this->selectedTrimesterId;
        match ($this->qualitativeType) {
            'career_guidance' => $this->loadCareerGuidanceData($yearId, $subjectId, $gradeId, $trimesterId),
            'classroom_support' => $this->loadClassroomSupportData($yearId, $subjectId, $gradeId, $trimesterId),
            'reading_promotion' => $this->loadReadingPromotionData($yearId, $subjectId, $gradeId, $trimesterId),
            default => null,
        };
    }

    protected function loadCareerGuidanceData(int $yearId, int $subjectId, int $gradeId, int $trimesterId): void
    {
        $eje = $this->getEjeForGrade($gradeId);

        $this->qualitativeIndicators = CareerGuidanceIndicator::where(fn ($q) => $q->where('grade_id', $gradeId)->orWhereNull('grade_id'))
            ->when($eje, fn ($q) => $q->where('eje', $eje))
            ->orderBy('order')->get()->toArray();

        $studentIds = $this->getStudentIds();
        $existing = CareerGuidance::where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)->where('trimester_id', $trimesterId)
            ->where('year_id', $yearId)->whereIn('student_id', $studentIds)
            ->get()->keyBy(fn ($g) => $g->student_id . '_' . $g->indicator_id);
        $this->qualitativeGrades = [];
        foreach ($studentIds as $sid) {
            foreach ($this->qualitativeIndicators as $ind) {
                $key = $sid . '_' . $ind['id'];
                $this->qualitativeGrades[$key] = $existing->has($key) ? $existing[$key]->value : null;
            }
        }
    }

    protected function loadClassroomSupportData(int $yearId, int $subjectId, int $gradeId, int $trimesterId): void
    {
        $this->qualitativeIndicators = IntegralClassroomSupportIndicator::orderBy('order')->get()->toArray();
        $studentIds = $this->getStudentIds();
        $existing = IntegralClassroomSupport::where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)->where('trimester_id', $trimesterId)
            ->where('year_id', $yearId)->whereIn('student_id', $studentIds)
            ->get()->keyBy(fn ($g) => $g->student_id . '_' . $g->skill_id);
        $this->qualitativeGrades = [];
        foreach ($studentIds as $sid) {
            foreach ($this->qualitativeIndicators as $ind) {
                $key = $sid . '_' . $ind['id'];
                $this->qualitativeGrades[$key] = $existing->has($key) ? $existing[$key]->value : null;
            }
        }
    }

    protected function loadReadingPromotionData(int $yearId, int $subjectId, int $gradeId, int $trimesterId): void
    {
        $this->qualitativeIndicators = ReadingPromotionIndicator::orderBy('order')->get()->toArray();
        $studentIds = $this->getStudentIds();
        $existing = ReadingPromotion::where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)->where('trimester_id', $trimesterId)
            ->where('year_id', $yearId)->whereIn('student_id', $studentIds)
            ->get()->keyBy(fn ($g) => $g->student_id . '_' . $g->indicator_id);
        $this->qualitativeGrades = [];
        foreach ($studentIds as $sid) {
            foreach ($this->qualitativeIndicators as $ind) {
                $key = $sid . '_' . $ind['id'];
                $this->qualitativeGrades[$key] = $existing->has($key) ? $existing[$key]->value : null;
            }
        }
    }

    public function saveQualitativeGrade($studentId, $indicatorId, $value): void
    {
        if (!$this->isGradingOpen()) {
            Flux::toast(variant: 'danger', text: __('No se pueden guardar calificaciones. El periodo de calificacion esta cerrado.'));
            return;
        }
        if (!$this->isQualitativeSubject() || !$this->selectedTrimesterId || $this->selectedTrimesterId == self::SUPLETORIO_MODE) {
            return;
        }
        $type = $this->getQualitativeType();
        $yearId = $this->yearId;
        $subjectId = $this->selectedSubjectId;
        $gradeId = $this->selectedGradeId;
        $trimesterId = $this->selectedTrimesterId;

        if ($type === 'reading_promotion') {
            $value = $value !== '' ? (int) $value : null;
            if ($value !== null && ($value < 1 || $value > 10)) {
                Flux::toast(variant: 'danger', text: __('El valor debe estar entre 1 y 10.'));
                return;
            }
        } else {
            $value = $value !== '' ? strtoupper($value) : null;
        }

        match ($type) {
            'career_guidance' => CareerGuidance::updateOrCreate(
                ['student_id' => $studentId, 'indicator_id' => $indicatorId, 'subject_id' => $subjectId,
                 'grade_id' => $gradeId, 'trimester_id' => $trimesterId, 'year_id' => $yearId],
                ['value' => $value, 'recorded_by' => auth()->id()]
            ),
            'classroom_support' => IntegralClassroomSupport::updateOrCreate(
                ['student_id' => $studentId, 'skill_id' => $indicatorId, 'subject_id' => $subjectId,
                 'grade_id' => $gradeId, 'trimester_id' => $trimesterId, 'year_id' => $yearId],
                ['value' => $value, 'recorded_by' => auth()->id()]
            ),
            'reading_promotion' => ReadingPromotion::updateOrCreate(
                ['student_id' => $studentId, 'indicator_id' => $indicatorId, 'subject_id' => $subjectId,
                 'grade_id' => $gradeId, 'trimester_id' => $trimesterId, 'year_id' => $yearId],
                ['value' => $value, 'recorded_by' => auth()->id()]
            ),
            default => null,
        };
        $key = $studentId . '_' . $indicatorId;
        $this->qualitativeGrades[$key] = $value;
    }

    public function isGradingOpen(): bool
    {
        if ($this->isQualitativeSubject()) {
            if (!$this->selectedTrimesterId || $this->selectedTrimesterId == self::SUPLETORIO_MODE) {
                return false;
            }
            $period = \App\Models\Setting\YearSettings\AcademicPeriod::find($this->selectedTrimesterId);
            return $period ? $period->isGradingOpen() : false;
        }
        if ($this->activeBlockId === '_supletorios') {
            return $this->isSupletorioAvailable();
        }
        if (!$this->selectedTrimesterId || $this->selectedTrimesterId == self::SUPLETORIO_MODE) {
            return false;
        }
        $period = \App\Models\Setting\YearSettings\AcademicPeriod::find($this->selectedTrimesterId);
        if (!$period) {
            return false;
        }
        return $period->isGradingOpen();
    }

    public function isSupletorioAvailable(): bool
    {
        $regularTrimesters = collect($this->trimesters)->filter(fn ($t) => !($t['is_supletorio'] ?? false));
        if ($regularTrimesters->count() < 3) {
            return false;
        }
        foreach ($regularTrimesters as $t) {
            $period = \App\Models\Setting\YearSettings\AcademicPeriod::find($t['id']);
            if ($period && !$period->isGradingPast()) {
                return false;
            }
        }
        return true;
    }

    public function isSumativaAvailable(int $trimesterId): bool
    {
        $period = \App\Models\Setting\YearSettings\AcademicPeriod::find($trimesterId);
        if (!$period) {
            return false;
        }
        return $period->isGradingPast() || $period->isGradingOpen();
    }

    public function getSubjectName(): string
    {
        return $this->currentSchedule()['subject']['subject_name'] ?? '';
    }

    public function getGradeName(): string
    {
        $schedule = $this->currentSchedule();
        if (!$schedule || !$schedule['grade']) {
            return '';
        }
        return ($schedule['grade']['grade_name'] ?? '') . ' ' . ($schedule['grade']['section'] ?? '');
    }

    public function getShiftName(): string
    {
        return $this->currentSchedule()['grade']['nivel']['shift']['shift_name'] ?? '';
    }

    protected function currentSchedule(): ?array
    {
        return collect($this->schedules)->first(fn ($s) =>
            (int) $s['subject_id'] === (int) $this->selectedSubjectId
            && (int) $s['grade_id'] === (int) $this->selectedGradeId
        );
    }

    protected function getEjeForGrade(?int $gradeId): ?string
    {
        if (!$gradeId) {
            return null;
        }

        $grade = \App\Models\Setting\EducationalSettings\Grade::find($gradeId);
        if (!$grade) {
            return null;
        }

        $name = strtolower($grade->grade_name);

        if (str_contains($name, '8')) {
            return 'Autoconocimiento';
        }
        if (str_contains($name, '9')) {
            return 'Informacion';
        }
        if (str_contains($name, '10')) {
            return 'Toma de decisiones';
        }

        return null;
    }

    public function getStudentCount(): int
    {
        if (!$this->selectedGradeId) {
            return 0;
        }
        return \App\Models\Management\Enrollments\StudentEnrollment::where('grade_id', $this->selectedGradeId)
            ->where('year_id', $this->yearId)->count();
    }

    public function getBlockAverageForDisplay($blockId)
    {
        $block = $this->assessmentBlocks->firstWhere('id', $blockId);
        if (!$block || $block->activities->count() === 0) {
            return null;
        }
        $total = 0;
        foreach ($block->activities as $activity) {
            $grades = $activity->grades->pluck('grade')->filter()->values();
            if ($grades->count() > 0) {
                $total += $grades->avg();
            }
        }
        return $total / $block->activities->count();
    }

    public function getActivityAverage($activityId)
    {
        $activity = $this->assessmentBlocks->flatMap->activities->firstWhere('id', $activityId);
        if (!$activity) {
            return null;
        }
        $grades = $activity->grades->pluck('grade')->filter();
        return $grades->count() > 0 ? $grades->avg() : null;
    }

    public function isReadingPromotion(): bool
    {
        return $this->qualitativeType === 'reading_promotion';
    }

    public function calculateQualitativeAverage(int $studentId): ?string
    {
        if (empty($this->qualitativeIndicators)) {
            return null;
        }
        $type = $this->qualitativeType;

        if ($type === 'reading_promotion') {
            $sum = 0;
            $count = 0;
            foreach ($this->qualitativeIndicators as $ind) {
                $key = $studentId . '_' . $ind['id'];
                $val = $this->qualitativeGrades[$key] ?? null;
                if ($val !== null && $val !== '' && is_numeric($val)) {
                    $sum += (int) $val;
                    $count++;
                }
            }
            if ($count === 0) {
                return null;
            }
            $avg = ceil($sum / count($this->qualitativeIndicators));
            foreach (self::READING_LETTER_TABLE as $range) {
                if ($avg >= $range['min'] && $avg <= $range['max']) {
                    return $range['letter'];
                }
            }
            return null;
        }

        $sum = 0;
        $hasValue = false;
        foreach ($this->qualitativeIndicators as $ind) {
            $key = $studentId . '_' . $ind['id'];
            $val = $this->qualitativeGrades[$key] ?? null;
            if ($val && isset(self::QUAL_VALUE_MAP[$val])) {
                $sum += self::QUAL_VALUE_MAP[$val];
                $hasValue = true;
            }
        }
        if (!$hasValue) {
            return null;
        }
        foreach (self::QUAL_LETTER_TABLE as $range) {
            if ($sum >= $range['min'] && $sum <= $range['max']) {
                return $range['letter'];
            }
        }
        return null;
    }

    public function calculateQualitativeAverageFromGrades(array $grades, array $indicators): ?string
    {
        $sum = 0;
        $hasValue = false;
        foreach ($indicators as $ind) {
            $key = $ind['id'] ?? $ind;
            foreach ($grades as $gKey => $gVal) {
                if (str_ends_with($gKey, '_' . $key) && isset(self::QUAL_VALUE_MAP[$gVal])) {
                    $sum += self::QUAL_VALUE_MAP[$gVal];
                    $hasValue = true;
                    break;
                }
            }
        }
        if (!$hasValue) {
            return null;
        }
        foreach (self::QUAL_LETTER_TABLE as $range) {
            if ($sum >= $range['min'] && $sum <= $range['max']) {
                return $range['letter'];
            }
        }
        return null;
    }
}; ?>

<div>
    @include('pages.system.teachers-management.teachers.gradebook.⚡gradebook-header')

    @if($selectedSubjectId && $selectedGradeId)
        @if($this->isQualitativeSubject())
            @include('pages.system.teachers-management.teachers.gradebook.⚡qualitative-grade-table')
        @else
            @include('pages.system.teachers-management.teachers.gradebook.⚡numerical-grade-tabs')

            @if($activeTab === 'supletorios')
                @include('pages.system.teachers-management.teachers.gradebook.⚡supletorio-view')
            @elseif($activeTab === 'sumativa')
                @include('pages.system.teachers-management.teachers.gradebook.⚡sumativa-view')
            @elseif($activeBlockId && $activeBlockId !== '_supletorios')
                @include('pages.system.teachers-management.teachers.gradebook.⚡block-detail-view')
            @else
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                    <flux:icon.document-text class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-sm text-zinc-500">{{ __('Seleccione un bloque o pestana para ver los detalles.') }}</p>
                </div>
            @endif
        @endif
    @else
        <div class="text-center py-16 text-zinc-400">
            <flux:icon.book-open class="mx-auto mb-4 size-16 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('Seleccione una asignatura y grado para comenzar') }}</p>
        </div>
    @endif

    @include('pages.system.teachers-management.teachers.gradebook.⚡gradebook-modals')
</div>
