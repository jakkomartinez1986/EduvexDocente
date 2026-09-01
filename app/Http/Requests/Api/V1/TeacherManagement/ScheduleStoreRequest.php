<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\TeacherManagement;

use Illuminate\Foundation\Http\FormRequest;

final class ScheduleStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year_id' => ['required', 'integer', 'exists:scolar_years,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'schedule_type' => ['required', 'string', 'in:OFFICIAL,EVALUATION,TEST,MAKEUP'],
            'day' => ['required', 'string', 'in:LUNES,MARTES,MIERCOLES,MIÉRCOLES,JUEVES,VIERNES,SABADO'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'trimester_id' => [
                'nullable',
                'integer',
                'exists:academic_periods,id',
                'required_if:schedule_type,EVALUATION',
                'required_if:schedule_type,MAKEUP',
                'prohibited_unless:schedule_type,EVALUATION,MAKEUP',
            ],
            'classroom' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'year_id' => 'año lectivo',
            'subject_id' => 'asignatura',
            'grade_id' => 'grado',
            'schedule_type' => 'tipo de horario',
            'day' => 'día',
            'start_time' => 'hora de inicio',
            'end_time' => 'hora de fin',
            'trimester_id' => 'trimestre',
        ];
    }
}
