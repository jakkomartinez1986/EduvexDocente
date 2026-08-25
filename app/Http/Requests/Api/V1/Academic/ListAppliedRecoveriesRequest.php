<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academic;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros del historial de recuperaciones aplicadas por trimestre.
 */
final class ListAppliedRecoveriesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trimester_id' => ['required', 'integer'],
            'year_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'trimester_id' => 'trimester_id',
            'year_id' => 'year_id',
        ];
    }
}
