<?php

namespace App\Models\Setting\YearSettings;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $year_id
 * @property string $trimester_name
 * @property string $start_date
 * @property string $end_date
 * @property string|null $grading_open_date
 * @property string|null $grading_close_date
 * @property bool $is_supletorio
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'year_id', 'trimester_name', 'start_date', 'end_date',
    'grading_open_date', 'grading_close_date', 'is_supletorio', 'status',
])]
class AcademicPeriod extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'year_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'grading_open_date' => 'date',
            'grading_close_date' => 'date',
            'is_supletorio' => 'boolean',
            'status' => 'integer',
        ];
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(ScolarYear::class, 'year_id');
    }

    public function isActive(): bool
    {
        return $this->status === 1;
    }

    public function isGradingOpen(): bool
    {
        if (! $this->grading_open_date || ! $this->grading_close_date) {
            return $this->isActive();
        }

        $now = now()->toDateString();

        return $now >= $this->grading_open_date->toDateString()
            && $now <= $this->grading_close_date->toDateString();
    }

    public function isGradingPast(): bool
    {
        if (! $this->grading_close_date) {
            return ! $this->isActive();
        }

        return now()->toDateString() > $this->grading_close_date->toDateString();
    }
}
