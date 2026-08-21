<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Setting;

use App\Models\Setting\YearSettings\AcademicPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin AcademicPeriod
 */
final class AcademicPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AcademicPeriod $period */
        $period = $this->resource;

        return [
            'id' => $period->id,
            'year_id' => $period->year_id,
            'trimester_name' => $period->trimester_name,
            'start_date' => Carbon::parse($period->start_date)->toDateString(),
            'end_date' => Carbon::parse($period->end_date)->toDateString(),
            'grading_open_date' => $period->grading_open_date
                ? Carbon::parse($period->grading_open_date)->toDateString()
                : null,
            'grading_close_date' => $period->grading_close_date
                ? Carbon::parse($period->grading_close_date)->toDateString()
                : null,
            'is_supletorio' => (bool) $period->is_supletorio,
            'status' => (int) $period->status,
            'grading_is_open' => $period->isGradingOpen(),
            'grading_is_past' => $period->isGradingPast(),
        ];
    }
}
