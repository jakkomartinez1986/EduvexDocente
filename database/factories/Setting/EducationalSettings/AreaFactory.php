<?php

namespace Database\Factories\Setting\EducationalSettings;

use App\Models\Setting\EducationalSettings\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'area_name' => $this->faker->randomElement(['Matemáticas', 'Lengua Extranjera', 'Ciencias Naturales', 'Ciencias Sociales']),
        ];
    }
}
