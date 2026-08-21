<?php

namespace App\Models\Setting\YearSettings;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $year_name
 * @property string $start_date
 * @property string $end_date
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['year_name', 'start_date', 'end_date', 'status'])]
class ScolarYear extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => 'integer',
        ];
    }

    public function academicPeriods(): HasMany
    {
        return $this->hasMany(AcademicPeriod::class, 'year_id');
    }

    public function gradingSchemes(): HasMany
    {
        return $this->hasMany(GradingScheme::class, 'year_id');
    }

    public function calendarDays(): HasMany
    {
        return $this->hasMany(CalendarDay::class, 'year_id');
    }

    public function isActive(): bool
    {
        return $this->status === 1;
    }
}
