<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Sync;

use Illuminate\Foundation\Http\FormRequest;

final class SyncPushRequest extends FormRequest
{
    /**
     * Límite del lote (synchronization.md §4): máx 200 operaciones.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'uuid'],
            'operations' => ['required', 'array', 'min:1', 'max:200'],
            'operations.*.operation_id' => ['required', 'string', 'uuid'],
            'operations.*.entity' => ['required', 'string', 'max:64'],
            'operations.*.action' => ['required', 'string', 'max:32'],
            'operations.*.client_updated_at' => ['nullable', 'date'],
            'operations.*.force' => ['sometimes', 'boolean'],
            'operations.*.payload' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'device_id' => 'device_id',
            'operations' => 'operations',
            'operations.*.operation_id' => 'operation_id',
            'operations.*.entity' => 'entity',
            'operations.*.action' => 'action',
            'operations.*.force' => 'force',
            'operations.*.payload' => 'payload',
        ];
    }
}
