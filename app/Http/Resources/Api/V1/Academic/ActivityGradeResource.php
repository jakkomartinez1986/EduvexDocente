<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityGrade
 */
final class ActivityGradeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ActivityGrade $grade */
        $grade = $this->resource;

        return [
            'id' => $grade->id,
            'activity_id' => $grade->activity_id,
            'student_id' => $grade->student_id,
            'grade' => $grade->grade !== null ? (float) $grade->grade : null,
            'recorded_by' => $grade->recorded_by,
        ];
    }
}
