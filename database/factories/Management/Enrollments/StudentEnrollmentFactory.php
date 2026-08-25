<?php

namespace Database\Factories\Management\Enrollments;

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\YearSettings\ScolarYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentEnrollment>
 *
 * Por defecto la matrícula está activa en el año del grado indicado.
 */
class StudentEnrollmentFactory extends Factory
{
    public function definition(): array
    {
        /** @var Grade $grade */
        $grade = Grade::factory()->create();

        /** @var ScolarYear $year */
        $year = ScolarYear::factory()->create();

        return [
            'student_id' => Student::factory(),
            'grade_id' => $grade->id,
            'year_id' => $year->id,
            'enrollment_date' => $year->start_date,
            'completion_date' => null,
            'status' => 'active',
            'academic_year' => $year->year_name,
            'notes' => null,
        ];
    }
}
