<?php

namespace App\Models\Setting\EducationalSettings;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $nivel_id
 * @property string $grade_name
 * @property string|null $section
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['nivel_id', 'grade_name', 'section', 'status'])]
class Grade extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'nivel_id' => 'integer',
            'status' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Nivel, $this>
     */
    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class);
    }

    public function parallels(): HasMany
    {
        return $this->hasMany(Parallel::class);
    }

    public function isActive(): bool
    {
        return $this->status === 1;
    }
}
