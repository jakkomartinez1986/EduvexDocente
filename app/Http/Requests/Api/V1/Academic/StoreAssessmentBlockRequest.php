<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academic;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAssessmentBlockRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'internal_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
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
            'name' => 'name',
            'internal_percentage' => 'internal_percentage',
        ];
    }
}
