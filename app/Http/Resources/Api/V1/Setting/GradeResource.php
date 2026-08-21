<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Setting;

use App\Models\Setting\EducationalSettings\Grade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Grade
 */
final class GradeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Grade $grade */
        $grade = $this->resource;

        return [
            'id' => $grade->id,
            'nivel_id' => $grade->nivel_id,
            'grade_name' => $grade->grade_name,
            'section' => $grade->section,
            'status' => (int) $grade->status,
            'nivel' => $this->whenLoaded('nivel', fn () => new NivelResource($grade->nivel)),
        ];
    }
}
