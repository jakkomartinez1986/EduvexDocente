<?php

namespace Database\Factories\Setting\EducationalSettings;

use App\Models\Setting\EducationalSettings\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'area_id' => AreaFactory::new(),
            'subject_name' => $this->faker->randomElement(['Matemática', 'Inglés', 'Biología', 'Historia', 'Física']),
        ];
    }
}
