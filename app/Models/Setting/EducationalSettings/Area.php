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
 * @property string $area_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['area_name'])]
class Area extends Model
{
    use HasFactory, SoftDeletes;

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}
