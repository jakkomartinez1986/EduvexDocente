<?php

namespace Database\Factories\Setting\EducationalSettings;

use App\Models\Setting\EducationalSettings\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nivel_id' => NivelFactory::new(),
            'grade_name' => $this->faker->randomElement(['Octavo', 'Noveno', 'Décimo', 'Primero', 'Segundo', 'Tercero']),
            'section' => 'A',
            'status' => 1,
        ];
    }
}
