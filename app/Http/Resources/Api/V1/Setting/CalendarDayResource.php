<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Setting;

use App\Models\Setting\YearSettings\CalendarDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin CalendarDay
 */
final class CalendarDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CalendarDay $day */
        $day = $this->resource;

        return [
            'id' => $day->id,
            'year_id' => $day->year_id,
            'trimester_id' => $day->trimester_id,
            'period' => $day->period,
            'date' => Carbon::parse($day->date)->toDateString(),
            'month_name' => $day->month_name,
            'day_name' => $day->day_name,
            'week' => $day->week,
            'day_number' => $day->day_number,
            'activity' => $day->activity,
            'is_holiday' => (bool) $day->is_holiday,
        ];
    }
}
