<?php

namespace Database\Factories\Setting\YearSettings;

use App\Models\Setting\YearSettings\ScolarYear;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ScolarYear>
 *
 * Por defecto el año se crea INACTIVO para que nunca robe el rol de
 * "año lectivo activo" cuando una factory padre lo genera como residuo
 * (AcademicYearService resuelve el activo por status + year_name mayor).
 * Los tests que necesiten un año activo deben usar el estado active().
 */
class ScolarYearFactory extends Factory
{
    public function definition(): array
    {
        $start = Carbon::parse(fake()->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'));

        return [
            'year_name' => $start->format('Y'),
            'start_date' => $start->copy()->subDays(30)->toDateString(),
            'end_date' => $start->copy()->addMonths(9)->toDateString(),
            'status' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => 1,
        ]);
    }
}
