<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentExam
 */
final class StudentExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StudentExam $exam */
        $exam = $this->resource;

        return [
            'id' => $exam->id,
            'subject_id' => $exam->subject_id,
            'grade_id' => $exam->grade_id,
            'trimester_id' => $exam->trimester_id,
            'year_id' => $exam->year_id,
            'student_id' => $exam->student_id,
            'grade' => $exam->grade !== null ? (float) $exam->grade : null,
            'recorded_by' => $exam->recorded_by,
        ];
    }
}
