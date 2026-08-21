<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use Illuminate\Foundation\Http\FormRequest;

final class StoreRecoveryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer'],
            'recovery_grade' => ['required', 'numeric', 'min:0', 'max:10'],
            'update_method' => ['nullable', 'string', 'in:'.implode(',', array_keys(ActivityRecovery::METHODS))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'student_id' => 'student_id',
            'recovery_grade' => 'recovery_grade',
            'update_method' => 'update_method',
        ];
    }
}
