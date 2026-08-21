<?php

namespace App\Models\Setting\EducationalSettings;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $classroom_name
 * @property string $type
 * @property int $capacity
 * @property int|null $floor
 * @property string|null $location
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'code', 'classroom_name', 'type', 'capacity',
    'floor', 'location', 'status',
])]
class Classroom extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'floor' => 'integer',
            'status' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 1;
    }
}
