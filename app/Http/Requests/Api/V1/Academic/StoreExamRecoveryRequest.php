<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academic;

use App\Models\Academic\GradeBook\Summaries\Supplementary\ExamRecovery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Registro de una recuperación del examen sumativo de un trimestre.
 */
final class StoreExamRecoveryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer'],
            'grade_id' => ['required', 'integer'],
            'trimester_id' => ['required', 'integer'],
            'year_id' => ['nullable', 'integer'],
            'student_id' => ['required', 'integer'],
            'recovery_grade' => ['required', 'numeric', 'min:0', 'max:20'],
            'update_method' => ['nullable', 'string', 'in:'.implode(',', array_keys(ExamRecovery::METHODS))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subject_id' => 'subject_id',
            'grade_id' => 'grade_id',
            'trimester_id' => 'trimester_id',
            'year_id' => 'year_id',
            'student_id' => 'student_id',
            'recovery_grade' => 'recovery_grade',
            'update_method' => 'update_method',
        ];
    }
}
