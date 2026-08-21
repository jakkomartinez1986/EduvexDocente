<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Activity
 */
final class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Activity $activity */
        $activity = $this->resource;

        return [
            'id' => $activity->id,
            'assessment_block_id' => $activity->assessment_block_id,
            'name' => $activity->name,
            'description' => $activity->description,
            'date' => $activity->date ? Carbon::parse($activity->date)->toDateString() : null,
            'max_score' => $activity->max_score,
            'status' => (bool) $activity->status,
            'grades' => $this->whenLoaded(
                'grades',
                fn () => ActivityGradeResource::collection($activity->grades),
            ),
        ];
    }
}
