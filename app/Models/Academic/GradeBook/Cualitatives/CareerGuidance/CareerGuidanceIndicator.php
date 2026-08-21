<?php

namespace App\Models\Academic\GradeBook\Cualitatives\CareerGuidance;

use App\Models\Setting\EducationalSettings\Grade;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $eje
 * @property int|null $grade_id
 * @property string|null $description
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'eje', 'grade_id', 'description', 'order'])]
class CareerGuidanceIndicator extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'grade_id' => 'integer',
        ];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function careerGuidances(): HasMany
    {
        return $this->hasMany(CareerGuidance::class, 'indicator_id');
    }
}
