<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Setting\YearSettings\GradingScheme;
use Illuminate\Support\Collection;

final class GradeCalculationService
{
    public function studentBlockAverage(Activity $activity, int $studentId): ?float
    {
        $total = 0;
        $count = 0;

        foreach ($activity->grades as $grade) {
            if ($grade->student_id === $studentId && $grade->grade !== null) {
                $total += $grade->grade;
                $count++;
            }
        }

        return $count > 0 ? floor($total / $count * 100) / 100 : null;
    }

    public function blockAverageForStudent(AssessmentBlock $block, int $studentId): ?float
    {
        if ($block->activities->isEmpty()) {
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

    public function formativeAverage(Collection $assessmentBlocks, int $studentId): ?float
    {
        $blockAverages = [];

        foreach ($assessmentBlocks as $block) {
            $avg = $this->blockAverageForStudent($block, $studentId);
            if ($avg !== null) {
                $blockAverages[] = $avg;
            }
        }

        if (count($blockAverages) === 0) {
            return null;
        }

        return floor(array_sum($blockAverages) / count($blockAverages) * 100) / 100;
    }

    public function totalAverage(
        Collection $assessmentBlocks,
        ?GradingScheme $gradingScheme,
        int $studentId,
        ?float $examGrade,
        ?float $projectGrade,
    ): ?float {
        if (! $gradingScheme) {
            return null;
        }

        $formativeAvg = $this->formativeAverage($assessmentBlocks, $studentId);

        if ($formativeAvg === null && $examGrade === null && $projectGrade === null) {
            return null;
        }

        $formativeWeighted = $formativeAvg !== null
            ? $formativeAvg * ($gradingScheme->formative_percentage / 100)
            : 0;
        $examWeighted = $examGrade !== null
            ? $examGrade * ($gradingScheme->exam_percentage / 100)
            : 0;
        $projectWeighted = $projectGrade !== null
            ? $projectGrade * ($gradingScheme->project_percentage / 100)
            : 0;

        return floor(($formativeWeighted + $examWeighted + $projectWeighted) * 100) / 100;
    }

    public function trimesterFormativeAverage(
        Collection $blocks,
        int $studentId,
    ): ?float {
        $blockAverages = [];

        foreach ($blocks as $block) {
            $totalActivities = $block->activities->count();
            if ($totalActivities === 0) {
                continue;
            }

            $total = 0;
            foreach ($block->activities as $activity) {
                $grade = $activity->grades->firstWhere('student_id', $studentId);
                if ($grade && $grade->grade !== null) {
                    $total += $grade->grade;
                }
            }
            $blockAverages[] = $total / $totalActivities;
        }

        if (count($blockAverages) === 0) {
            return null;
        }

        return floor(array_sum($blockAverages) / count($blockAverages) * 100) / 100;
    }

    public function trimesterTotal(
        Collection $blocks,
        ?GradingScheme $gradingScheme,
        int $studentId,
        ?float $examGrade,
        ?float $projectGrade,
    ): ?float {
        if (! $gradingScheme) {
            return null;
        }

        $formativeAvg = $this->trimesterFormativeAverage($blocks, $studentId);

        $formativeWeighted = $formativeAvg !== null
            ? $formativeAvg * ($gradingScheme->formative_percentage / 100)
            : 0;
        $examWeighted = $examGrade !== null
            ? $examGrade * ($gradingScheme->exam_percentage / 100)
            : 0;
        $projectWeighted = $projectGrade !== null
            ? $projectGrade * ($gradingScheme->project_percentage / 100)
            : 0;

        $total = $formativeWeighted + $examWeighted + $projectWeighted;

        if ($total == 0 && ! $formativeAvg && ! $examGrade && ! $projectGrade) {
            return null;
        }

        return round($total, 2);
    }

    public function blockAverageForDisplay(AssessmentBlock $block): ?float
    {
        if ($block->activities->count() === 0) {
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

    public function activityAverage(Activity $activity): ?float
    {
        $grades = $activity->grades->pluck('grade')->filter();

        return $grades->count() > 0 ? $grades->avg() : null;
    }

    public function performanceColor(?float $average): string
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

    public function performanceBgColor(?float $average): string
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

    public function statusFor(?float $total): string
    {
        if ($total === null) {
            return 'Sin datos';
        }
        if ($total >= 7) {
            return 'Aprobado';
        }
        if ($total >= 5) {
            return 'Supletorio';
        }

        return 'Reprobado';
    }
}
