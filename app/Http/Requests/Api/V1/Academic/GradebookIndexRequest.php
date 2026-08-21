<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academic;

use Illuminate\Foundation\Http\FormRequest;

final class GradebookIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year_id' => ['nullable', 'integer', 'exists:scolar_years,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'trimester_id' => ['nullable', 'integer', 'exists:academic_periods,id'],
        ];
    }
}
