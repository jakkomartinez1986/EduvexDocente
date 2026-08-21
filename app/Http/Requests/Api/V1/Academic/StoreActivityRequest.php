<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Academic;

use Illuminate\Foundation\Http\FormRequest;

final class StoreActivityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assessment_block_id' => ['required', 'integer', 'exists:assessment_blocks,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['nullable', 'date'],
            'max_score' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'assessment_block_id' => 'assessment_block_id',
            'name' => 'name',
            'max_score' => 'max_score',
        ];
    }
}
