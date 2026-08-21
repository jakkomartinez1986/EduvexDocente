<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Setting;

use App\Models\Setting\EducationalSettings\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subject
 */
final class SubjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Subject $subject */
        $subject = $this->resource;

        return [
            'id' => $subject->id,
            'area_id' => $subject->area_id,
            'subject_name' => $subject->subject_name,
            'area' => $this->whenLoaded('area', fn () => new AreaResource($subject->area)),
        ];
    }
}
