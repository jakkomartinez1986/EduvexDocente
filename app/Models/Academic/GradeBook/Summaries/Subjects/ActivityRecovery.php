<?php

namespace App\Models\Academic\GradeBook\Summaries\Subjects;

use App\Models\Identity\Users\Student;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $activity_id
 * @property int $student_id
 * @property int|null $year_id
 * @property int|null $recorded_by
 * @property int $attempt_number
 * @property float $original_grade
 * @property float $recovery_grade
 * @property string $update_method
 * @property float|null $final_grade
 * @property bool $is_applied
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'activity_id', 'student_id', 'year_id', 'recorded_by',
    'attempt_number', 'original_grade', 'recovery_grade',
    'update_method', 'final_grade', 'is_applied', 'applied_at',
])]
class ActivityRecovery extends Model
{
    use HasFactory, SoftDeletes;

    public const METHOD_AVERAGE = 'average';

    public const METHOD_HIGHEST = 'highest';

    public const METHODS = [
        self::METHOD_AVERAGE => 'Promedio',
        self::METHOD_HIGHEST => 'La mas alta',
    ];

    protected function casts(): array
    {
        return [
            'activity_id' => 'integer',
            'student_id' => 'integer',
            'year_id' => 'integer',
            'recorded_by' => 'integer',
            'attempt_number' => 'integer',
            'original_grade' => 'float',
            'recovery_grade' => 'float',
            'final_grade' => 'float',
            'is_applied' => 'boolean',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(ScolarYear::class, 'year_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->update_method] ?? $this->update_method;
    }

    public static function computeFinalGrade(float $originalGrade, float $recoveryGrade, string $method): float
    {
        return match ($method) {
            self::METHOD_HIGHEST => round(max($originalGrade, $recoveryGrade), 2),
            default => round(($originalGrade + $recoveryGrade) / 2, 2),
        };
    }
}
