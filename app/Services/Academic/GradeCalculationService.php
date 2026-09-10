<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
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

    /**
     * @param  Collection<int, AssessmentBlock>  $assessmentBlocks
     */
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

    /**
     * @param  Collection<int, AssessmentBlock>  $assessmentBlocks
     */
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

    /**
     * @param  Collection<int, AssessmentBlock>  $blocks
     */
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

    /**
     * @param  Collection<int, AssessmentBlock>  $blocks
     */
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

    /**
     * Agregados trimestrales de una clase completa. Fuente única de verdad
     * para el SFC del gradebook (replica EXACTA de ensureTrimesterAggregates):
     *
     * - `formative[{studentId}]`: promedio de bloques, redondeado con floor por
     *   bloque y por trimestre, saltando bloques sin actividades.
     * - `total[{studentId}]`: ponderado formative/examen/proyecto usando el
     *   promedio NO redondeado de bloques, con la condición de "sin datos".
     *
     * Pura (sin queries): recibe las colecciones ya cargadas para que el
     * resultado sea cacheable y testeable con fábricas.
     *
     * @param  Collection<int, AssessmentBlock>  $blocks
     * @param  Collection<int, StudentExam>|Collection<int, StudentProject>  $exams  keyBy student_id
     * @param  Collection<int, StudentExam>|Collection<int, StudentProject>  $projects  keyBy student_id
     * @param  array<int, int>  $studentIds
     * @return array{formative: array<int, float|null>, total: array<int, float|null>, hasData: bool}
     */
    public function classTrimesterAggregates(
        Collection $blocks,
        Collection $exams,
        Collection $projects,
        array $studentIds,
        ?GradingScheme $gradingScheme,
    ): array {
        $formativePercentage = $gradingScheme ? (float) $gradingScheme->formative_percentage / 100 : 0;
        $examPercentage = $gradingScheme ? (float) $gradingScheme->exam_percentage / 100 : 0;
        $projectPercentage = $gradingScheme ? (float) $gradingScheme->project_percentage / 100 : 0;

        $formative = [];
        $total = [];

        foreach ($studentIds as $sid) {
            $blockAverages = [];
            foreach ($blocks as $block) {
                if ($block->activities->count() === 0) {
                    continue;
                }
                $sum = 0;
                foreach ($block->activities as $activity) {
                    $grade = $activity->grades->firstWhere('student_id', $sid);
                    if ($grade && $grade->grade !== null) {
                        $sum += $grade->grade;
                    }
                }
                $blockAverages[] = $sum / $block->activities->count();
            }

            if (count($blockAverages) > 0) {
                $formative[$sid] = floor(array_sum($blockAverages) / count($blockAverages) * 100) / 100;
            }

            $rawAvg = count($blockAverages) > 0 ? array_sum($blockAverages) / count($blockAverages) : null;
            $exam = $exams[$sid] ?? null;
            $project = $projects[$sid] ?? null;

            $summed = ($rawAvg !== null ? $rawAvg * $formativePercentage : 0)
                + ($exam && $exam->grade !== null ? $exam->grade * $examPercentage : 0)
                + ($project && $project->grade !== null ? $project->grade * $projectPercentage : 0);

            if ($summed == 0 && ! $rawAvg && ! $exam && ! $project) {
                $total[$sid] = null;
            } else {
                $total[$sid] = round($summed, 2);
            }
        }

        return [
            'formative' => $formative,
            'total' => $total,
            'hasData' => $blocks->count() > 0 || $exams->count() > 0 || $projects->count() > 0,
        ];
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
