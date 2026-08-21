<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academic;

use Illuminate\Foundation\Http\FormRequest;

final class GradebookViewRequest extends FormRequest
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
        ];
    }
}
