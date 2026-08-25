<?php

namespace Database\Factories\Setting\EducationalSettings;

use App\Models\Setting\EducationalSettings\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shift_name' => $this->faker->randomElement(['Matutina', 'Vespertina', 'Nocturna']),
            'status' => 1,
        ];
    }
}
