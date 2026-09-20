<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\TeacherManagement;

use App\Models\TeacherManagement\Attendances\ClassObservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'novedad' => ['nullable', 'string', 'max:1000'],
            'novedad_type' => ['nullable', 'string', Rule::in(ClassObservation::NOVEDAD_TYPES)],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['required', 'string', 'in:P,A,I,J,AI,AA,N'],
            'observations' => ['nullable', 'array'],
            'observations.*' => ['nullable', 'string', 'max:1000'],
            'novedades' => ['nullable', 'array'],
            'novedades.*' => ['nullable', 'string', 'max:1000'],
            'novedad_types' => ['nullable', 'array'],
            'novedad_types.*' => ['nullable', 'string', Rule::in(ClassObservation::NOVEDAD_TYPES)],
            'client_uuids' => ['nullable', 'array'],
            'client_uuids.*' => ['nullable', 'uuid'],
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
            'novedad' => 'novedad',
            'novedad_type' => 'novedad_type',
            'statuses' => 'statuses',
            'observations' => 'observations',
            'novedades' => 'novedades',
            'novedad_types' => 'novedad_types',
            'client_uuids' => 'client_uuids',
        ];
    }
}
