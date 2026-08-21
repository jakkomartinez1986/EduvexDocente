<?php

namespace App\Models\TeacherManagement\Attendances;

use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $class_observation_id
 * @property int|null $class_schedule_id
 * @property int $calendarday_id
 * @property int|null $year_id
 * @property int|null $tutor_id
 * @property int $student_id
 * @property string $date
 * @property string|null $status
 * @property Carbon|null $arrival_time
 * @property string|null $justification
 * @property string|null $justification_file_path
 * @property string|null $observation
 * @property int $recorded_by
 * @property array|null $notification_data
 * @property Carbon|null $notification_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'class_observation_id', 'class_schedule_id', 'calendarday_id', 'year_id',
    'tutor_id', 'student_id', 'date', 'status', 'arrival_time',
    'justification', 'justification_file_path', 'observation',
    'recorded_by', 'notification_data', 'notification_sent_at',
])]
class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'class_observation_id' => 'integer',
            'class_schedule_id' => 'integer',
            'calendarday_id' => 'integer',
            'year_id' => 'integer',
            'tutor_id' => 'integer',
            'student_id' => 'integer',
            'date' => 'date',
            'arrival_time' => 'datetime:H:i',
            'recorded_by' => 'integer',
            'notification_data' => 'array',
            'notification_sent_at' => 'datetime',
        ];
    }

    public function classObservation(): BelongsTo
    {
        return $this->belongsTo(ClassObservation::class);
    }

    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function calendarDay(): BelongsTo
    {
        return $this->belongsTo(CalendarDay::class, 'calendarday_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(ScolarYear::class, 'year_id');
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'tutor_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
