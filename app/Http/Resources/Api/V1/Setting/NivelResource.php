<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Setting;

use App\Models\Setting\EducationalSettings\Nivel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Nivel
 */
final class NivelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Nivel $nivel */
        $nivel = $this->resource;

        return [
            'id' => $nivel->id,
            'shift_id' => $nivel->shift_id,
            'nivel_name' => $nivel->nivel_name,
            'status' => (int) $nivel->status,
            'shift' => $this->whenLoaded('shift', fn () => new ShiftResource($nivel->shift)),
        ];
    }
}
