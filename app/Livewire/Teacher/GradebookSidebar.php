<?php

declare(strict_types=1);

namespace App\Livewire\Teacher;

use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Services\AcademicYearService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GradebookSidebar extends Component
{
    public int $yearId;

    public ?int $selectedSubjectId = null;

    public ?int $selectedGradeId = null;

    public mixed $selectedTrimesterId = null;

    public mixed $activeBlockId = null;

    /** @var array<int, AcademicPeriod|null> mapa memoizado de períodos por id */
    protected array $periodMap = [];

    public function selectBlock(mixed $blockId): void
    {
        $this->dispatch('gradebook-select-block', blockId: $blockId);
    }

    public function openCreateBlock(): void
    {
        $this->dispatch('gradebook-open-create-block');
    }

    public function openEditBlock($blockId): void
    {
        $this->dispatch('gradebook-open-edit-block', blockId: $blockId);
    }

    public function deleteBlock($blockId): void
    {
        $this->dispatch('gradebook-delete-block', blockId: $blockId);
    }

    #[Computed]
    public function sidebarItems(): array
    {
        $items = [];
        $blocks = $this->getBlocks();

        foreach ($blocks as $block) {
            $activities = $block->activities ?? collect();
            $blockAvg = $this->computeBlockAverage($block);

            $items[] = (object) [
                'id' => $block->id,
                'type' => 'block',
                'name' => $block->name,
                'percentage' => $block->internal_percentage,
                'act_count' => $activities->count(),
                'average' => $blockAvg,
                'status' => null,
            ];
        }

        if ($this->selectedTrimesterId && $this->selectedTrimesterId != -1) {
            $period = $this->period((int) $this->selectedTrimesterId);

            if ($this->isSumativaAvailable((int) $this->selectedTrimesterId)) {
                $items[] = (object) [
                    'id' => '_sumativa_'.$this->selectedTrimesterId,
                    'type' => 'sumativa',
                    'name' => __('Sumativa').' — '.($period?->trimester_name ?? ''),
                    'percentage' => null,
                    'act_count' => null,
                    'average' => null,
                    'status' => $period && $period->isGradingPast() ? 'Cerrado' : ($period && $period->isGradingOpen() ? 'Abierto' : null),
                ];
            }

            if ($this->isSupletorioAvailable()) {
                $items[] = (object) [
                    'id' => '_supletorios',
                    'type' => 'supletorio',
                    'name' => __('Supletorio'),
                    'percentage' => null,
                    'act_count' => null,
                    'average' => null,
                    'status' => __('Recuperacion'),
                ];
            }
        }

        return $items;
    }

    protected function getBlocks()
    {
        if (! $this->selectedSubjectId || ! $this->selectedGradeId || ! $this->selectedTrimesterId) {
            return collect();
        }

        $studentIds = StudentEnrollment::where('grade_id', $this->selectedGradeId)
            ->where('year_id', $this->yearId)
            ->pluck('student_id')
            ->toArray();

        return AssessmentBlock::where('year_id', $this->yearId)
            ->where('subject_id', $this->selectedSubjectId)
            ->where('grade_id', $this->selectedGradeId)
            ->where('trimester_id', $this->selectedTrimesterId)
            ->where('teacher_id', auth()->user()->teacher?->id)
            ->with(['activities.grades' => function ($q) use ($studentIds) {
                $q->whereIn('student_id', $studentIds);
            }])
            ->orderBy('order')
            ->orderBy('created_at')
            ->get();
    }

    protected function computeBlockAverage($block): ?float
    {
        if (! $block || $block->activities->count() === 0) {
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

    protected function isSumativaAvailable(int $trimesterId): bool
    {
        $period = $this->period($trimesterId);
        if (! $period) {
            return false;
        }

        return $period->isGradingPast() || $period->isGradingOpen();
    }

    protected function isSupletorioAvailable(): bool
    {
        $trimesters = $this->getTrimesters();
        $regularTrimesters = collect($trimesters)->filter(fn ($t) => ! ($t['is_supletorio'] ?? false));

        if ($regularTrimesters->count() < 3) {
            return false;
        }

        foreach ($regularTrimesters as $t) {
            $period = $this->period($t['id']);
            if ($period && ! $period->isGradingPast()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lookup memoizado de AcademicPeriod por id (evita N+1 en la render del
     * sidebar a lo largo de isSumativaAvailable/isSupletorioAvailable).
     */
    protected function period(int $id): ?AcademicPeriod
    {
        return $this->periodMap[$id] ??= AcademicPeriod::find($id);
    }

    protected function getTrimesters(): array
    {
        $year = app(AcademicYearService::class)->getActiveYear();
        if (! $year) {
            return [];
        }

        return $year->academicPeriods()
            ->where('status', 1)
            ->get()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.teacher.gradebook-sidebar');
    }
}
