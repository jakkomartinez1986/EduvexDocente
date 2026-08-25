<?php

namespace Database\Factories\Setting\YearSettings;

use App\Models\Setting\YearSettings\CalendarDay;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CalendarDay>
 */
class CalendarDayFactory extends Factory
{
    public function definition(): array
    {
        $date = Carbon::parse($this->faker->dateTimeThisYear());

        return [
            'year_id' => ScolarYearFactory::new(),
            'trimester_id' => null,
            'period' => null,
            'date' => $date->toDateString(),
            'month_name' => ucfirst($date->translatedFormat('F')),
            'day_name' => strtoupper($date->locale('es')->dayName),
            'week' => $date->weekOfMonth,
            'day_number' => (int) $date->format('N'),
            'activity' => null,
            'is_holiday' => false,
        ];
    }
}
