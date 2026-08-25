<?php

namespace Database\Factories\Academic\GradeBook\Summaries\Subjects;

use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Teacher;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentBlock>
 */
class AssessmentBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'grade_id' => Grade::factory(),
            'trimester_id' => AcademicPeriod::factory(),
            'year_id' => ScolarYear::factory(),
            'teacher_id' => Teacher::factory(),
            'name' => 'Bloque '.$this->faker->numberBetween(1, 5),
            'description' => null,
            'order' => 1,
            'internal_percentage' => null,
            'is_active' => true,
        ];
    }
}
