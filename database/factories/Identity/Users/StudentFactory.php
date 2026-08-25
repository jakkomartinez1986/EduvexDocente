<?php

namespace Database\Factories\Identity\Users;

use App\Models\Identity\Users\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'student_code' => 'EST-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'enrollment_date' => $this->faker->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'birth_date' => $this->faker->dateTimeBetween('-17 years', '-11 years')->format('Y-m-d'),
            'blood_type' => $this->faker->randomElement(['O+', 'O-', 'A+', 'A-', 'B+', 'B-']),
            'emergency_contact' => $this->faker->numerify('09########'),
            'medical_info' => null,
        ];
    }
}
