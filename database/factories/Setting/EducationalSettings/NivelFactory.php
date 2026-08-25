<?php

namespace Database\Factories\Setting\EducationalSettings;

use App\Models\Setting\EducationalSettings\Nivel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nivel>
 */
class NivelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shift_id' => ShiftFactory::new(),
            'nivel_name' => $this->faker->randomElement(['Básica Elemental', 'Básica Media', 'Básica Superior', 'Bachillerato']),
            'status' => 1,
        ];
    }
}
