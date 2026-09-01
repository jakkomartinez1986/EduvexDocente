<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Sync;

use Illuminate\Foundation\Http\FormRequest;

final class SyncPullRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'collections' => [
                'sometimes',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $items = array_filter(array_map('trim', explode(',', (string) $value)));

                    if ($items === []) {
                        $fail('Debes solicitar al menos una colección.');

                        return;
                    }

                    foreach ($items as $item) {
                        if (! in_array($item, ['attendance', 'gradebook'], true)) {
                            $fail("Colección no soportada: {$item}.");

                            return;
                        }
                    }
                },
            ],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'collections' => 'collections',
            'cursor' => 'cursor',
            'limit' => 'limit',
        ];
    }
}
