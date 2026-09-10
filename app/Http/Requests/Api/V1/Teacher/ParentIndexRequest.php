<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ParentIndexRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'grade_id' => ['nullable', 'integer', Rule::exists('grades', 'id')],
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')],
        ];
    }

    public function gradeId(): ?int
    {
        $gradeId = $this->validated('grade_id');

        return $gradeId === null ? null : (int) $gradeId;
    }

    public function studentId(): ?int
    {
        $studentId = $this->validated('student_id');

        return $studentId === null ? null : (int) $studentId;
    }
}
