<?php

namespace Database\Factories\Academic\GradeBook\Summaries\Subjects;

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Identity\Users\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityGrade>
 */
class ActivityGradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'activity_id' => ActivityFactory::new(),
            'student_id' => Student::factory(),
            'grade' => $this->faker->randomFloat(2, 1, 10),
            'recorded_by' => User::factory(),
        ];
    }
}
