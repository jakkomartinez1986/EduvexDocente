<?php

namespace App\Models\Setting\YearSettings;

use Database\Factories\Setting\YearSettings\GradingSchemeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $year_id
 * @property float $formative_percentage
 * @property float $summative_percentage
 * @property float $exam_percentage
 * @property float $project_percentage
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'year_id', 'formative_percentage', 'summative_percentage',
    'exam_percentage', 'project_percentage', 'status',
])]
class GradingScheme extends Model
{
    /** @use HasFactory<GradingSchemeFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'grading_schemes';

    protected function casts(): array
    {
        return [
            'year_id' => 'integer',
            'formative_percentage' => 'float',
            'summative_percentage' => 'float',
            'exam_percentage' => 'float',
            'project_percentage' => 'float',
            'status' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ScolarYear, $this>
     */
    public function year(): BelongsTo
    {
        return $this->belongsTo(ScolarYear::class, 'year_id');
    }

    public function isActive(): bool
    {
        return $this->status === 1;
    }
}
