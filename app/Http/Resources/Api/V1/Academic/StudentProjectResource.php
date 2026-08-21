<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentProject
 */
final class StudentProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StudentProject $project */
        $project = $this->resource;

        return [
            'id' => $project->id,
            'subject_id' => $project->subject_id,
            'grade_id' => $project->grade_id,
            'trimester_id' => $project->trimester_id,
            'year_id' => $project->year_id,
            'student_id' => $project->student_id,
            'grade' => $project->grade !== null ? (float) $project->grade : null,
            'recorded_by' => $project->recorded_by,
        ];
    }
}
