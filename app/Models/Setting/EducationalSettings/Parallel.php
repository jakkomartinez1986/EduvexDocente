<?php

namespace App\Models\Setting\EducationalSettings;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $grade_id
 * @property string $code
 * @property string $parallel_name
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['grade_id', 'code', 'parallel_name', 'status'])]
class Parallel extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'grade_id' => 'integer',
            'status' => 'integer',
        ];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function isActive(): bool
    {
        return $this->status === 1;
    }
}
