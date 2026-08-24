<?php

namespace Database\Factories\Setting\EducationalSettings;

use App\Models\Setting\EducationalSettings\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_school' => 'Unidad Educativa '.$this->faker->unique()->city(),
            'distrit' => 'Distrito '.$this->faker->numberBetween(1, 9),
            'location' => $this->faker->city(),
            'address' => $this->faker->address(),
            'phone' => $this->faker->numerify('0#########'),
            'email' => $this->faker->safeEmail(),
            'website' => null,
            'logo_path' => null,
            'report_logo_path' => null,
            'status' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => 0,
        ]);
    }
}
