<?php

namespace App\Models\TeacherManagement\Academics;

use App\Models\Identity\Users\Teacher;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Attendances\Attendance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $year_id
 * @property int $teacher_id
 * @property int $subject_id
 * @property int $grade_id
 * @property int|null $trimester_id
 * @property int|null $calendarday_id
 * @property string $schedule_type
 * @property string $day
 * @property string $start_time
 * @property string $end_time
 * @property string|null $classroom
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'year_id', 'teacher_id', 'subject_id', 'grade_id', 'trimester_id',
    'calendarday_id', 'schedule_type', 'day', 'start_time', 'end_time',
    'classroom', 'is_active', 'notes',
])]
class ClassSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'year_id' => 'integer',
            'teacher_id' => 'integer',
            'subject_id' => 'integer',
            'grade_id' => 'integer',
            'trimester_id' => 'integer',
            'calendarday_id' => 'integer',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ScolarYear, $this>
     */
    public function year(): BelongsTo
    {
        return $this->belongsTo(ScolarYear::class, 'year_id');
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
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

    /**
     * @return BelongsTo<AcademicPeriod, $this>
     */
    public function trimester(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'trimester_id');
    }

    /**
     * @return BelongsTo<CalendarDay, $this>
     */
    public function calendarDay(): BelongsTo
    {
        return $this->belongsTo(CalendarDay::class, 'calendarday_id');
    }

    public function classObservations(): HasMany
    {
        return $this->hasMany(ClassObservation::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}
