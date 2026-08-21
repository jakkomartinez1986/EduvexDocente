<?php

namespace App\Models\Setting\EducationalSettings;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $shift_name
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['shift_name', 'status'])]
class Shift extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    public function nivels(): HasMany
    {
        return $this->hasMany(Nivel::class);
    }

    public function isActive(): bool
    {
        return $this->status === 1;
    }
}
