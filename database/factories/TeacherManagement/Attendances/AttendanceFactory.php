<?php

namespace Database\Factories\TeacherManagement\Attendances;

use App\Models\Identity\Users\Student;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        /** @var ClassSchedule $schedule */
        $schedule = ClassSchedule::factory()->create();

        return [
            'class_observation_id' => null,
            'class_schedule_id' => $schedule->id,
            'calendarday_id' => CalendarDay::factory()->create([
                'year_id' => $schedule->year_id,
                'date' => now()->toDateString(),
            ])->id,
            'year_id' => $schedule->year_id,
            'tutor_id' => null,
            'teacher_id' => $schedule->teacher_id,
            'student_id' => Student::factory(),
            'date' => now()->toDateString(),
            'client_uuid' => (string) Str::uuid(),
            'status' => $this->faker->randomElement(['A', 'I', 'J', 'AI', 'AA', 'N']),
            'arrival_time' => null,
            'justification' => null,
            'justification_file_path' => null,
            'novedad' => null,
            'novedad_type' => null,
            'observation' => null,
            'recorded_by' => User::factory(),
            'recorded_at' => now(),
        ];
    }
}
