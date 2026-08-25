<?php

namespace Database\Factories\TeacherManagement\Attendances;

use App\Models\Identity\Users\Teacher;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassObservation>
 */
class ClassObservationFactory extends Factory
{
    public function definition(): array
    {
        /** @var ClassSchedule $schedule */
        $schedule = ClassSchedule::factory()->create();

        return [
            'class_schedule_id' => $schedule->id,
            'tutor_id' => null,
            'teacher_id' => Teacher::factory(),
            'year_id' => $schedule->year_id,
            'observation_date' => now()->toDateString(),
            'classtopic' => $this->faker->sentence(4),
            'observation' => $this->faker->sentence(8),
            'novedad' => null,
            'novedad_type' => null,
        ];
    }
}
