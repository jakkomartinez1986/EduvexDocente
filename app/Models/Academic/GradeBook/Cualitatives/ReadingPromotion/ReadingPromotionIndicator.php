<?php

namespace App\Models\Academic\GradeBook\Cualitatives\ReadingPromotion;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'description', 'order'])]
class ReadingPromotionIndicator extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function readingPromotions(): HasMany
    {
        return $this->hasMany(ReadingPromotion::class, 'indicator_id');
    }
}
