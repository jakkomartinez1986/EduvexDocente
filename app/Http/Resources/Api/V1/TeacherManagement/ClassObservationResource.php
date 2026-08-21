<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\TeacherManagement;

use App\Models\TeacherManagement\Attendances\ClassObservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin ClassObservation
 */
final class ClassObservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ClassObservation $observation */
        $observation = $this->resource;

        return [
            'id' => $observation->id,
            'class_schedule_id' => $observation->class_schedule_id,
            'tutor_id' => $observation->tutor_id,
            'teacher_id' => $observation->teacher_id,
            'year_id' => $observation->year_id,
            'observation_date' => Carbon::parse($observation->observation_date)->toDateString(),
            'classtopic' => $observation->classtopic,
            'observation' => $observation->observation,
            'class_observation' => $observation->class_observation,
            'novedad' => $observation->novedad,
            'novedad_type' => $observation->novedad_type,
        ];
    }
}
