<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\TeacherManagement;

use Illuminate\Foundation\Http\FormRequest;

final class AttendanceSummaryIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year_id' => ['nullable', 'integer', 'exists:scolar_years,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:class_schedules,id'],
            'trimester_id' => ['nullable', 'integer', 'exists:academic_periods,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'year_id' => 'year_id',
            'schedule_id' => 'schedule_id',
            'trimester_id' => 'trimester_id',
            'date_from' => 'date_from',
            'date_to' => 'date_to',
        ];
    }
}
