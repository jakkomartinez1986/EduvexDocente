<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academic;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros para el listado de estudiantes recuperables de una actividad o del examen.
 */
final class ListRecoverableRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:activity,exam'],
            'activity_id' => ['required_if:type,activity', 'integer'],
            'subject_id' => ['required_if:type,exam', 'integer'],
            'grade_id' => ['required_if:type,exam', 'integer'],
            'trimester_id' => ['required_if:type,exam', 'integer'],
            'year_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'type',
            'activity_id' => 'activity_id',
            'subject_id' => 'subject_id',
            'grade_id' => 'grade_id',
            'trimester_id' => 'trimester_id',
            'year_id' => 'year_id',
        ];
    }
}
