<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityRecovery
 */
final class ActivityRecoveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ActivityRecovery $recovery */
        $recovery = $this->resource;

        return [
            'id' => $recovery->id,
            'activity_id' => $recovery->activity_id,
            'student_id' => $recovery->student_id,
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
