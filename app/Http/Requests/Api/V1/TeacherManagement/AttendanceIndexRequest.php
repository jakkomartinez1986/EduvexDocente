<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\TeacherManagement;

use Illuminate\Foundation\Http\FormRequest;

final class AttendanceIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year_id' => ['nullable', 'integer', 'exists:scolar_years,id'],
            'date' => ['nullable', 'date'],
            'schedule_id' => ['nullable', 'integer', 'exists:class_schedules,id'],
        ];
    }
}
