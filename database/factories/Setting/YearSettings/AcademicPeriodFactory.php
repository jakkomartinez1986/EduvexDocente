<?php

namespace Database\Factories\Setting\YearSettings;

use App\Models\Setting\YearSettings\AcademicPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AcademicPeriod>
 *
 * Por defecto el período está activo y con la ventana de calificación abierta
 * (hoy entre grading_open_date y grading_close_date).
 */
class AcademicPeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'year_id' => ScolarYearFactory::new(),
            'trimester_name' => $this->faker->unique()->randomElement(['Primer Trimestre', 'Segundo Trimestre', 'Tercer Trimestre']),
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->addDays(60)->toDateString(),
            'grading_open_date' => now()->subDays(30)->toDateString(),
            'grading_close_date' => now()->addDays(60)->toDateString(),
            'is_supletorio' => false,
            'status' => 1,
        ];
    }

    /**
     * Ventana de calificación cerrada (ya pasó).
     */
    public function gradingClosed(): static
    {
        return $this->state(fn (): array => [
            'start_date' => now()->subDays(90)->toDateString(),
            'end_date' => now()->subDays(10)->toDateString(),
            'grading_open_date' => now()->subDays(90)->toDateString(),
            'grading_close_date' => now()->subDays(5)->toDateString(),
        ]);
    }

    /**
     * Aún no abre la ventana de calificación.
     */
    public function notYetOpen(): static
    {
        return $this->state(fn (): array => [
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(90)->toDateString(),
            'grading_open_date' => now()->addDays(20)->toDateString(),
            'grading_close_date' => now()->addDays(80)->toDateString(),
        ]);
    }

    /**
     * Período inactivo (status = 0).
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => 0,
        ]);
    }

    /**
     * Período de exámenes supletorios de fin de año.
     */
    public function supletorio(): static
    {
        return $this->state(fn (): array => [
            'trimester_name' => 'Supletorios '.now()->year,
            'start_date' => Carbon::parse(now()->endOfYear())->subDays(10)->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'grading_open_date' => Carbon::parse(now()->endOfYear())->subDays(10)->toDateString(),
            'grading_close_date' => now()->endOfYear()->toDateString(),
            'is_supletorio' => true,
        ]);
    }
}
