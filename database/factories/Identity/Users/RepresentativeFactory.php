<?php

namespace Database\Factories\Identity\Users;

use App\Models\Identity\Users\Representative;
use App\Models\Identity\Users\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Representative>
 */
class RepresentativeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'student_id' => Student::factory(),
            'relationship' => $this->faker->randomElement(['Padre', 'Madre', 'Tutor', 'Representante']),
            'occupation' => $this->faker->jobTitle(),
            'work_phone' => $this->faker->numerify('0##########'),
            'geolocation_info' => null,
        ];
    }
}
