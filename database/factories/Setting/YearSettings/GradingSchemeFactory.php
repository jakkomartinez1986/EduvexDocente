<?php

namespace Database\Factories\Setting\YearSettings;

use App\Models\Setting\YearSettings\GradingScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingScheme>
 *
 * Esquema por defecto: formativa 80% + examen 14% + proyecto 6% = 100%.
 */
class GradingSchemeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'year_id' => ScolarYearFactory::new(),
            'formative_percentage' => 80.0,
            'summative_percentage' => 0.0,
            'exam_percentage' => 14.0,
            'project_percentage' => 6.0,
            'status' => 1,
        ];
    }
}
