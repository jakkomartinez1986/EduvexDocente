<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Setting;

use App\Models\Setting\EducationalSettings\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin School
 */
final class SchoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var School $school */
        $school = $this->resource;

        return [
            'id' => $school->id,
            'name' => $school->name_school,
            'distrit' => $school->distrit,
            'location' => $school->location,
            'address' => $school->address,
            'phone' => $school->phone,
            'email' => $school->email,
            'website' => $school->website,
            'logo_path' => $school->logo_path,
            'report_logo_path' => $school->report_logo_path,
            'status' => (int) $school->status,
        ];
    }
}
