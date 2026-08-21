<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\TeacherManagement;

use Illuminate\Foundation\Http\FormRequest;

final class ScheduleIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year_id' => ['nullable', 'integer', 'exists:scolar_years,id'],
            'schedule_type' => ['nullable', 'string', 'in:OFFICIAL,EVALUATION,TEST,MAKEUP'],
            'day' => ['nullable', 'string', 'in:LUNES,MARTES,MIERCOLES,JUEVES,VIERNES,SABADO'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'year_id' => 'year_id',
            'schedule_type' => 'schedule_type',
            'day' => 'day',
        ];
    }
}
