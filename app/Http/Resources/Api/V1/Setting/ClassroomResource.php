<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Setting;

use App\Models\Setting\EducationalSettings\Classroom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Classroom
 */
final class ClassroomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Classroom $classroom */
        $classroom = $this->resource;

        return [
            'id' => $classroom->id,
            'code' => $classroom->code,
            'classroom_name' => $classroom->classroom_name,
            'type' => $classroom->type,
            'capacity' => $classroom->capacity,
            'floor' => $classroom->floor,
            'location' => $classroom->location,
            'status' => (int) $classroom->status,
        ];
    }
}
