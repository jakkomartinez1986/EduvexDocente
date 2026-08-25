<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Students;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StudentIndexRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'grade_id' => ['nullable', 'integer', Rule::exists('grades', 'id')],
        ];
    }

    public function gradeId(): ?int
    {
        $gradeId = $this->validated('grade_id');

        return $gradeId === null ? null : (int) $gradeId;
    }
}
