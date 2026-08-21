<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Setting;

use App\Http\Resources\Api\V1\Auth\UserResource;
use App\Models\Identity\Users\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Teacher
 */
final class TeacherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Teacher $teacher */
        $teacher = $this->resource;

        return [
            'id' => $teacher->id,
            'teacher_code' => $teacher->teacher_code,
            'specialization' => $teacher->specialization,
            'title' => $teacher->title,
            'education_level' => $teacher->education_level,
            'hire_date' => $teacher->hire_date
                ? Carbon::parse($teacher->hire_date)->toDateString()
                : null,
            'user' => $this->whenLoaded('user', fn () => new UserResource($teacher->user)),
        ];
    }
}
