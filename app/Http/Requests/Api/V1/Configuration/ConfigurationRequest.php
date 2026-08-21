<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Configuration;

use Illuminate\Foundation\Http\FormRequest;

final class ConfigurationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['nullable', 'string', 'max:64'],
        ];
    }
}
