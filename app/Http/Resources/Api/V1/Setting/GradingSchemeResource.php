<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Setting;

use App\Models\Setting\YearSettings\GradingScheme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GradingScheme
 */
final class GradingSchemeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GradingScheme $scheme */
        $scheme = $this->resource;

        return [
            'id' => $scheme->id,
            'year_id' => $scheme->year_id,
            'formative_percentage' => $scheme->formative_percentage,
            'summative_percentage' => $scheme->summative_percentage,
            'exam_percentage' => $scheme->exam_percentage,
            'project_percentage' => $scheme->project_percentage,
            'status' => (int) $scheme->status,
        ];
    }
}
