<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Setting\YearSettings\GradingScheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Cómputo compartido de los agregados de una clase para los reportes PDF.
 *
 * Los reportes (subjectAnnualReport, supletorioReport, studentAnnualReport...)
 * duplicaban inline el mismo cálculo de bloques/examen/proyecto y el total
 * ponderado. Este servicio concentra esa lógica para que todos usen la misma
 * fuente (cache-strategy.md §3), PRESERVANDO la semántica exacta de los
 * reportes: el promedio formativo se reduce con floor por bloque y por
 * trimestre, y el total se pondera con ese formativo ya redondeado.
 */
final class GradebookReportComputer
{
    /**
     * Agregados por estudiante de una clase en un trimestre, con la semántica
     * de los reportes (formativo con floor en el total).
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, array{formative: float|null, exam: float|null, project: float|null, total: float|null}>
     *                                                                                                            clave = student_id
     */
    public function classTrimesterAggregates(
        int $yearId,
        int $subjectId,
        int $gradeId,
        int $teacherId,
        int $trimesterId,
        array $studentIds,
        ?GradingScheme $scheme,
    ): array {
        $blocks = AssessmentBlock::query()
            ->where('year_id', $yearId)
            ->where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)
            ->where('trimester_id', $trimesterId)
            ->where('teacher_id', $teacherId)
            ->with(['activities.grades' => fn ($q) => $q->whereIn('student_id', $studentIds)])
            ->get();

        $exams = $this->loadExams($yearId, $subjectId, $gradeId, $trimesterId, $studentIds);
        $projects = $this->loadProjects($yearId, $subjectId, $gradeId, $trimesterId, $studentIds);

        $formativePercentage = $scheme ? (float) $scheme->formative_percentage / 100 : 0;
        $examPercentage = $scheme ? (float) $scheme->exam_percentage / 100 : 0;
        $projectPercentage = $scheme ? (float) $scheme->project_percentage / 100 : 0;

        $result = [];

        foreach ($studentIds as $studentId) {
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

                $blockAverages[] = floor($total / $totalActivities * 100) / 100;
            }

            $formativeAvg = count($blockAverages) > 0
                ? floor(array_sum($blockAverages) / count($blockAverages) * 100) / 100
                : null;

            $exam = $exams[$studentId] ?? null;
            $project = $projects[$studentId] ?? null;

            $fw = $formativeAvg !== null ? $formativeAvg * $formativePercentage : 0;
            $ew = $exam && $exam->grade !== null ? $exam->grade * $examPercentage : 0;
            $pw = $project && $project->grade !== null ? $project->grade * $projectPercentage : 0;

            $totalVal = $fw + $ew + $pw;
            $hasData = $formativeAvg !== null || ($exam && $exam->grade !== null) || ($project && $project->grade !== null);
            $examGrade = $exam?->grade !== null ? (float) $exam->grade : null;
            $projectGrade = $project?->grade !== null ? (float) $project->grade : null;

            $result[$studentId] = [
                'formative' => $formativeAvg,
                'exam' => $examGrade,
                'project' => $projectGrade,
                'total' => $hasData ? round($totalVal, 2) : null,
            ];
        }

        return $result;
    }

    /**
     * Carga batch de todo el gradebook de un grado/año para los reportes PDF.
     *
     * Hace 1 query de AssessmentBlock (con activities.grades eager-load) + 1 de
     * StudentExam + 1 de StudentProject, y agrupa los resultados por
     * "subject_id|trimester_id". Evita el N+1 de hacer Subject::find(),
     * AssessmentBlock::get(), StudentExam::first() y StudentProject::first()
     * dentro de loops sujetos x trimestres x estudiantes (C-01).
     *
     * @param  array<int, int>  $subjectIds
     * @param  array<int, int>  $trimesterIds
     * @param  array<int, int>  $studentIds
     * @return object{blocks: Collection, exams: Collection, projects: Collection}
     *                                                                             Collections groupBy "subject_id|trimester_id"; exams/projects además keyBy student_id.
     */
    public function loadClassData(
        int $yearId,
        int $gradeId,
        array $subjectIds,
        array $trimesterIds,
        array $studentIds,
    ): object {
        $blocks = AssessmentBlock::query()
            ->where('year_id', $yearId)
            ->where('grade_id', $gradeId)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('trimester_id', $trimesterIds)
            ->with(['activities.grades' => fn ($q) => $q->whereIn('student_id', $studentIds)])
            ->get()
            ->groupBy(fn ($b) => $b->subject_id.'|'.$b->trimester_id);

        $exams = $this->loadStudentGrades(StudentExam::class, $yearId, $gradeId, $subjectIds, $trimesterIds, $studentIds);
        $projects = $this->loadStudentGrades(StudentProject::class, $yearId, $gradeId, $subjectIds, $trimesterIds, $studentIds);

        return (object) compact('blocks', 'exams', 'projects');
    }

    /**
     * @param  class-string  $model
     * @param  array<int, int>  $subjectIds
     * @param  array<int, int>  $trimesterIds
     * @param  array<int, int>  $studentIds
     * @return Collection<string, Collection<int, Model>>
     *                                                    groupBy "subject_id|trimester_id" y keyBy student_id.
     */
    private function loadStudentGrades(
        string $model,
        int $yearId,
        int $gradeId,
        array $subjectIds,
        array $trimesterIds,
        array $studentIds,
    ): Collection {
        return $model::query()
            ->where('year_id', $yearId)
            ->where('grade_id', $gradeId)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('trimester_id', $trimesterIds)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy(fn ($e) => $e->subject_id.'|'.$e->trimester_id)
            ->map(fn ($group) => $group->keyBy('student_id'));
    }

    /**
     * Formativo floored por estudiante para una celda, reutilizando la semántica
     * de los reportes (misma reducción por bloque y por trimestre).
     *
     * @param  Collection<int, AssessmentBlock>  $blocks
     * @param  array<int, int>  $studentIds
     * @return array<int, float|null>
     */
    public function formativeByStudent(Collection $blocks, array $studentIds): array
    {
        $result = [];

        foreach ($studentIds as $studentId) {
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

                $blockAverages[] = floor($total / $totalActivities * 100) / 100;
            }

            $result[$studentId] = count($blockAverages) > 0
                ? floor(array_sum($blockAverages) / count($blockAverages) * 100) / 100
                : null;
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return Collection<int, StudentExam> keyBy student_id
     */
    private function loadExams(
        int $yearId,
        int $subjectId,
        int $gradeId,
        int $trimesterId,
        array $studentIds,
    ): Collection {
        return StudentExam::query()
            ->where('year_id', $yearId)
            ->where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)
            ->where('trimester_id', $trimesterId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return Collection<int, StudentProject> keyBy student_id
     */
    private function loadProjects(
        int $yearId,
        int $subjectId,
        int $gradeId,
        int $trimesterId,
        array $studentIds,
    ): Collection {
        return StudentProject::query()
            ->where('year_id', $yearId)
            ->where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)
            ->where('trimester_id', $trimesterId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');
    }
}
