<?php

namespace App\Models\Academic\GradeBook\Summaries\Subjects;

use App\Models\Identity\Users\Teacher;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subject_id
 * @property int $grade_id
 * @property int $trimester_id
 * @property int|null $year_id
 * @property int $teacher_id
 * @property string $name
 * @property string|null $description
 * @property int $order
 * @property float|null $internal_percentage
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'subject_id', 'grade_id', 'trimester_id', 'year_id', 'teacher_id',
    'name', 'description', 'order', 'internal_percentage', 'is_active',
])]
class AssessmentBlock extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'grade_id' => 'integer',
            'trimester_id' => 'integer',
            'year_id' => 'integer',
            'teacher_id' => 'integer',
            'order' => 'integer',
            'internal_percentage' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function trimester(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'trimester_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(ScolarYear::class, 'year_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}
