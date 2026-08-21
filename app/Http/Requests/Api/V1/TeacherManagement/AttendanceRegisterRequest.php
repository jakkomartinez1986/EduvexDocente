<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\TeacherManagement;

use Illuminate\Foundation\Http\FormRequest;

final class AttendanceRegisterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'integer', 'exists:class_schedules,id'],
            'date' => ['required', 'date'],
            'classtopic' => ['required', 'string', 'max:255'],
            'observation' => ['nullable', 'string', 'max:1000'],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['required', 'string', 'in:P,A,I,J,AI,AA'],
            'observations' => ['nullable', 'array'],
            'observations.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'schedule_id' => 'schedule_id',
            'date' => 'date',
            'classtopic' => 'classtopic',
            'observation' => 'observation',
            'statuses' => 'statuses',
            'observations' => 'observations',
        ];
    }
}
