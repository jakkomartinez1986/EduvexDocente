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
 * @property int|null $trimester_id
 * @property string|null $period
 * @property string $date
 * @property string|null $month_name
 * @property string $day_name
 * @property int|null $week
 * @property int|null $day_number
 * @property string|null $activity
 * @property bool $is_holiday
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'year_id', 'trimester_id', 'period', 'date', 'month_name',
    'day_name', 'week', 'day_number', 'activity', 'is_holiday',
])]
class CalendarDay extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'year_id' => 'integer',
            'trimester_id' => 'integer',
            'date' => 'date',
            'week' => 'integer',
            'day_number' => 'integer',
            'is_holiday' => 'boolean',
        ];
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(ScolarYear::class, 'year_id');
    }

    public function trimester(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'trimester_id');
    }
}
