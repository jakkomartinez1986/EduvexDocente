<?php

namespace App\Models\Academic\GradeBook\Summaries\Subjects;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_block_id
 * @property string $name
 * @property string|null $topic
 * @property string|null $description
 * @property string|null $date
 * @property float $max_score
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'assessment_block_id', 'name', 'topic', 'description', 'date',
    'max_score', 'status',
])]
class Activity extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'assessment_block_id' => 'integer',
            'date' => 'date',
            'max_score' => 'float',
            'status' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AssessmentBlock, $this>
     */
    public function assessmentBlock(): BelongsTo
    {
        return $this->belongsTo(AssessmentBlock::class);
    }

    /**
     * @return HasMany<ActivityGrade, $this>
     */
    public function activityGrades(): HasMany
    {
        return $this->hasMany(ActivityGrade::class);
    }

    /**
     * @return HasMany<ActivityGrade, $this>
     */
    public function grades(): HasMany
    {
        return $this->activityGrades();
    }

    public function isActive(): bool
    {
        return $this->status === true;
    }
}
