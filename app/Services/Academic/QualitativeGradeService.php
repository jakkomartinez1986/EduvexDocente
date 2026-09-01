<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\Academic\GradeBook\Cualitatives\CareerGuidance\CareerGuidance;
use App\Models\Academic\GradeBook\Cualitatives\CareerGuidance\CareerGuidanceIndicator;
use App\Models\Academic\GradeBook\Cualitatives\ClassroomSupport\IntegralClassroomSupport;
use App\Models\Academic\GradeBook\Cualitatives\ClassroomSupport\IntegralClassroomSupportIndicator;
use App\Models\Academic\GradeBook\Cualitatives\ReadingPromotion\ReadingPromotion;
use App\Models\Academic\GradeBook\Cualitatives\ReadingPromotion\ReadingPromotionIndicator;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use Illuminate\Support\Str;

final class QualitativeGradeService
{
    public const QUALITATIVE_VALUES = ['S', 'F', 'O', 'N'];

    public const QUALITATIVE_LABELS = [
        'S' => 'Siempre',
        'F' => 'Frecuentemente',
        'O' => 'Ocacionalmente',
        'N' => 'Nunca',
    ];

    private const QUAL_VALUE_MAP = ['S' => 4, 'F' => 3, 'O' => 2, 'N' => 1];

    private const QUAL_LETTER_TABLE = [
        ['min' => 35, 'max' => 36, 'letter' => 'A+'],
        ['min' => 33, 'max' => 34, 'letter' => 'A-'],
        ['min' => 30, 'max' => 32, 'letter' => 'B+'],
        ['min' => 27, 'max' => 29, 'letter' => 'B-'],
        ['min' => 20, 'max' => 26, 'letter' => 'C+'],
        ['min' => 18, 'max' => 19, 'letter' => 'C-'],
        ['min' => 15, 'max' => 17, 'letter' => 'D+'],
        ['min' => 13, 'max' => 14, 'letter' => 'D-'],
        ['min' => 11, 'max' => 12, 'letter' => 'E+'],
        ['min' => 0, 'max' => 10, 'letter' => 'E-'],
    ];

    private const READING_LETTER_TABLE = [
        ['min' => 9.01, 'max' => 10, 'letter' => 'A+'],
        ['min' => 8.01, 'max' => 9, 'letter' => 'A-'],
        ['min' => 7.01, 'max' => 8, 'letter' => 'B+'],
        ['min' => 6.01, 'max' => 7, 'letter' => 'B-'],
        ['min' => 5.01, 'max' => 6, 'letter' => 'C+'],
        ['min' => 4.01, 'max' => 5, 'letter' => 'C-'],
        ['min' => 3.01, 'max' => 4, 'letter' => 'D+'],
        ['min' => 2.01, 'max' => 3, 'letter' => 'D-'],
        ['min' => 1.01, 'max' => 2, 'letter' => 'E+'],
        ['min' => 0, 'max' => 1, 'letter' => 'E-'],
    ];

    public function isQualitativeSubject(?int $subjectId): bool
    {
        return $this->getQualitativeType($subjectId) !== '';
    }

    public function getQualitativeType(?int $subjectId): string
    {
        if (! $subjectId) {
            return '';
        }

        $subject = Subject::find($subjectId);
        if (! $subject) {
            return '';
        }

        $name = strtolower(Str::ascii($subject->subject_name));

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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loadIndicators(string $type, ?int $gradeId): array
    {
        return match ($type) {
            'career_guidance' => CareerGuidanceIndicator::where(fn ($q) => $q->where('grade_id', $gradeId)->orWhereNull('grade_id'))
                ->when($this->getEjeForGrade($gradeId), fn ($q) => $q->where('eje', $this->getEjeForGrade($gradeId)))
                ->orderBy('order')->get()->toArray(),
            'classroom_support' => IntegralClassroomSupportIndicator::orderBy('order')->get()->toArray(),
            'reading_promotion' => ReadingPromotionIndicator::orderBy('order')->get()->toArray(),
            default => [],
        };
    }

    private function getEjeForGrade(?int $gradeId): ?string
    {
        if (! $gradeId) {
            return null;
        }

        $grade = Grade::find($gradeId);
        if (! $grade) {
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

    /**
     * @param  array<int, int>  $studentIds
     * @param  array<int, array{id: int}>  $indicators
     * @return array<string, mixed>
     */
    public function loadGrades(
        string $type,
        int $yearId,
        int $subjectId,
        int $gradeId,
        int $trimesterId,
        array $studentIds,
        array $indicators,
    ): array {
        $grades = [];

        match ($type) {
            'career_guidance' => $grades = $this->loadCareerGuidanceGrades($yearId, $subjectId, $gradeId, $trimesterId, $studentIds, $indicators),
            'classroom_support' => $grades = $this->loadClassroomSupportGrades($yearId, $subjectId, $gradeId, $trimesterId, $studentIds, $indicators),
            'reading_promotion' => $grades = $this->loadReadingPromotionGrades($yearId, $subjectId, $gradeId, $trimesterId, $studentIds, $indicators),
            default => null,
        };

        return $grades;
    }

    /**
     * @param  array<int, int>  $studentIds
     * @param  array<int, array{id: int}>  $indicators
     * @return array<string, mixed>
     */
    private function loadCareerGuidanceGrades(int $yearId, int $subjectId, int $gradeId, int $trimesterId, array $studentIds, array $indicators): array
    {
        $existing = CareerGuidance::where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)
            ->where('trimester_id', $trimesterId)
            ->where('year_id', $yearId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy(fn ($g) => $g->student_id.'_'.$g->indicator_id);

        $grades = [];
        foreach ($studentIds as $sid) {
            foreach ($indicators as $ind) {
                $key = $sid.'_'.$ind['id'];
                $grades[$key] = $existing->has($key) ? $existing[$key]->value : null;
            }
        }

        return $grades;
    }

    /**
     * @param  array<int, int>  $studentIds
     * @param  array<int, array{id: int}>  $indicators
     * @return array<string, mixed>
     */
    private function loadClassroomSupportGrades(int $yearId, int $subjectId, int $gradeId, int $trimesterId, array $studentIds, array $indicators): array
    {
        $existing = IntegralClassroomSupport::where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)
            ->where('trimester_id', $trimesterId)
            ->where('year_id', $yearId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy(fn ($g) => $g->student_id.'_'.$g->skill_id);

        $grades = [];
        foreach ($studentIds as $sid) {
            foreach ($indicators as $ind) {
                $key = $sid.'_'.$ind['id'];
                $grades[$key] = $existing->has($key) ? $existing[$key]->value : null;
            }
        }

        return $grades;
    }

    /**
     * @param  array<int, int>  $studentIds
     * @param  array<int, array{id: int}>  $indicators
     * @return array<string, mixed>
     */
    private function loadReadingPromotionGrades(int $yearId, int $subjectId, int $gradeId, int $trimesterId, array $studentIds, array $indicators): array
    {
        $existing = ReadingPromotion::where('subject_id', $subjectId)
            ->where('grade_id', $gradeId)
            ->where('trimester_id', $trimesterId)
            ->where('year_id', $yearId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy(fn ($g) => $g->student_id.'_'.$g->indicator_id);

        $grades = [];
        foreach ($studentIds as $sid) {
            foreach ($indicators as $ind) {
                $key = $sid.'_'.$ind['id'];
                $grades[$key] = $existing->has($key) ? $existing[$key]->value : null;
            }
        }

        return $grades;
    }

    public function saveGrade(
        string $type,
        int $studentId,
        int $indicatorId,
        mixed $value,
        int $yearId,
        int $subjectId,
        int $gradeId,
        int $trimesterId,
    ): void {
        if ($type === 'reading_promotion') {
            $value = $value !== '' ? (int) $value : null;
        } else {
            $value = $value !== '' ? strtoupper($value) : null;
        }

        match ($type) {
            'career_guidance' => CareerGuidance::updateOrCreate(
                ['student_id' => $studentId, 'indicator_id' => $indicatorId, 'subject_id' => $subjectId, 'grade_id' => $gradeId, 'trimester_id' => $trimesterId, 'year_id' => $yearId],
                ['value' => $value, 'recorded_by' => auth()->id()]
            ),
            'classroom_support' => IntegralClassroomSupport::updateOrCreate(
                ['student_id' => $studentId, 'skill_id' => $indicatorId, 'subject_id' => $subjectId, 'grade_id' => $gradeId, 'trimester_id' => $trimesterId, 'year_id' => $yearId],
                ['value' => $value, 'recorded_by' => auth()->id()]
            ),
            'reading_promotion' => ReadingPromotion::updateOrCreate(
                ['student_id' => $studentId, 'indicator_id' => $indicatorId, 'subject_id' => $subjectId, 'grade_id' => $gradeId, 'trimester_id' => $trimesterId, 'year_id' => $yearId],
                ['value' => $value, 'recorded_by' => auth()->id()]
            ),
            default => null,
        };
    }

    /**
     * @param  array<int, array{id: int}>  $indicators
     * @param  array<string, mixed>  $grades
     */
    public function calculateAverage(int $studentId, string $type, array $indicators, array $grades): ?string
    {
        if (empty($indicators)) {
            return null;
        }

        if ($type === 'reading_promotion') {
            return $this->calculateReadingAverage($studentId, $indicators, $grades);
        }

        return $this->calculateSfoNAverage($studentId, $indicators, $grades);
    }

    /**
     * @param  array<int, array{id: int}>  $indicators
     * @param  array<string, mixed>  $grades
     */
    private function calculateReadingAverage(int $studentId, array $indicators, array $grades): ?string
    {
        $sum = 0;
        $count = 0;

        foreach ($indicators as $ind) {
            $key = $studentId.'_'.$ind['id'];
            $val = $grades[$key] ?? null;
            if ($val !== null && $val !== '' && is_numeric($val)) {
                $sum += (int) $val;
                $count++;
            }
        }

        if ($count === 0) {
            return null;
        }

        $avg = ceil($sum / count($indicators));

        foreach (self::READING_LETTER_TABLE as $range) {
            if ($avg >= $range['min'] && $avg <= $range['max']) {
                return $range['letter'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{id: int}>  $indicators
     * @param  array<string, mixed>  $grades
     */
    private function calculateSfoNAverage(int $studentId, array $indicators, array $grades): ?string
    {
        $sum = 0;
        $hasValue = false;

        foreach ($indicators as $ind) {
            $key = $studentId.'_'.$ind['id'];
            $val = $grades[$key] ?? null;
            if ($val && isset(self::QUAL_VALUE_MAP[$val])) {
                $sum += self::QUAL_VALUE_MAP[$val];
                $hasValue = true;
            }
        }

        if (! $hasValue) {
            return null;
        }

        foreach (self::QUAL_LETTER_TABLE as $range) {
            if ($sum >= $range['min'] && $sum <= $range['max']) {
                return $range['letter'];
            }
        }

        return null;
    }

    public function isReadingPromotion(string $type): bool
    {
        return $type === 'reading_promotion';
    }
}
