<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Setting;

use App\Models\Setting\YearSettings\ScolarYear;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin ScolarYear
 */
final class ScolarYearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ScolarYear $year */
        $year = $this->resource;

        return [
            'id' => $year->id,
            'year_name' => $year->year_name,
            'start_date' => Carbon::parse($year->start_date)->toDateString(),
            'end_date' => Carbon::parse($year->end_date)->toDateString(),
            'status' => (int) $year->status,
            'academic_periods' => $this->whenLoaded(
                'academicPeriods',
                fn () => AcademicPeriodResource::collection($year->academicPeriods),
            ),
            'grading_scheme' => $this->whenLoaded('gradingSchemes', fn () => $year->gradingSchemes->first()
                ? new GradingSchemeResource($year->gradingSchemes->first())
                : null),
            'calendar_days' => $this->whenLoaded(
                'calendarDays',
                fn () => CalendarDayResource::collection($year->calendarDays),
            ),
        ];
    }
}
