<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssessmentBlock
 */
final class AssessmentBlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AssessmentBlock $block */
        $block = $this->resource;

        return [
            'id' => $block->id,
            'subject_id' => $block->subject_id,
            'grade_id' => $block->grade_id,
            'trimester_id' => $block->trimester_id,
            'year_id' => $block->year_id,
            'teacher_id' => $block->teacher_id,
            'name' => $block->name,
            'description' => $block->description,
            'order' => (int) $block->order,
            'internal_percentage' => $block->internal_percentage !== null
                ? (float) $block->internal_percentage
                : null,
            'is_active' => (bool) $block->is_active,
            'activities' => $this->whenLoaded(
                'activities',
                fn () => ActivityResource::collection($block->activities),
            ),
        ];
    }
}
