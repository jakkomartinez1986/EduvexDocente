<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Setting;

use App\Models\Setting\EducationalSettings\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shift
 */
final class ShiftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Shift $shift */
        $shift = $this->resource;

        return [
            'id' => $shift->id,
            'shift_name' => $shift->shift_name,
            'status' => (int) $shift->status,
        ];
    }
}
