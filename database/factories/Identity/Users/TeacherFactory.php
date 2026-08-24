<?php

namespace Database\Factories\Identity\Users;

use App\Models\Identity\Users\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'teacher_code' => 'DOC-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'specialization' => $this->faker->randomElement(['Matemáticas', 'Lengua Extranjera', 'Biología', 'Física']),
            'hire_date' => $this->faker->dateTimeBetween('-10 years', '-1 year')->format('Y-m-d'),
            'title' => $this->faker->randomElement(['Licenciado', 'Ingeniero', 'Magíster']),
            'education_level' => $this->faker->randomElement(['Tercer Nivel', 'Cuarto Nivel']),
        ];
    }

    /**
     * Docente cuyo usuario tiene must_change_password = true.
     */
    public function withPendingPasswordChange(): static
    {
        return $this->state(fn (): array => [
            'user_id' => User::factory()->state([
                'must_change_password' => true,
            ]),
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Teacher $teacher): void {
            if ($teacher->hire_date !== null) {
                $teacher->hire_date = Carbon::parse($teacher->hire_date)->toDateString();
            }
        });
    }
}
