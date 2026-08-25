<?php

namespace Database\Factories\TeacherManagement\Academics;

use App\Models\Identity\Users\Teacher;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSchedule>
 */
class ClassScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'year_id' => ScolarYear::factory(),
            'teacher_id' => Teacher::factory(),
            'subject_id' => Subject::factory(),
            'grade_id' => Grade::factory(),
            'trimester_id' => null,
            'calendarday_id' => null,
            'schedule_type' => 'OFFICIAL',
            'day' => $this->faker->randomElement(['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES']),
            'start_time' => '07:00',
            'end_time' => '08:00',
            'classroom' => $this->faker->bothify('A-?#'),
            'is_active' => true,
            'notes' => null,
        ];
    }
}
