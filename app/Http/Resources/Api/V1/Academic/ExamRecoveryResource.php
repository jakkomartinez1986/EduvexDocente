<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Academic;

use App\Models\Academic\GradeBook\Summaries\Supplementary\ExamRecovery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExamRecovery
 */
final class ExamRecoveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ExamRecovery $recovery */
        $recovery = $this->resource;

        return [
            'id' => $recovery->id,
            'student_id' => $recovery->student_id,
            'subject_id' => $recovery->subject_id,
            'grade_id' => $recovery->grade_id,
            'trimester_id' => $recovery->trimester_id,
            'year_id' => $recovery->year_id,
            'attempt_number' => $recovery->attempt_number,
            'original_grade' => (float) $recovery->original_grade,
            'recovery_grade' => (float) $recovery->recovery_grade,
            'update_method' => $recovery->update_method,
            'final_grade' => $recovery->final_grade !== null ? (float) $recovery->final_grade : null,
            'is_applied' => (bool) $recovery->is_applied,
            'applied_at' => $recovery->applied_at?->toISOString(),
            'created_at' => $recovery->created_at?->toISOString(),
        ];
    }
}
