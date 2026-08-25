<?php

namespace Database\Factories\Academic\GradeBook\Summaries\Subjects;

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assessment_block_id' => AssessmentBlockFactory::new(),
            'name' => 'Actividad '.$this->faker->numberBetween(1, 10),
            'topic' => null,
            'description' => null,
            'date' => now()->toDateString(),
            'max_score' => 10.0,
            'status' => true,
        ];
    }
}
