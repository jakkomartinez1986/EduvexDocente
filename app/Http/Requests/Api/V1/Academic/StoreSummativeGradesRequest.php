<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academic;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSummativeGradesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year_id' => ['nullable', 'integer', 'exists:scolar_years,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'trimester_id' => ['required', 'integer', 'exists:academic_periods,id'],
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.student_id' => ['required', 'integer', 'distinct'],
            'grades.*.grade' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'year_id' => 'year_id',
            'subject_id' => 'subject_id',
            'grade_id' => 'grade_id',
            'trimester_id' => 'trimester_id',
            'grades' => 'grades',
        ];
    }
}
