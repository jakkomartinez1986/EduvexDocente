<?php

namespace App\Models\StudentManagement\Academics;

use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Incidents\NotificationChannel;
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
 * @property string|null $code
 * @property string|null $notification_number
 * @property string $type
 * @property string $channel
 * @property int $student_id
 * @property int $grade_id
 * @property int|null $subject_id
 * @property int $teacher_id
 * @property int $year_id
 * @property int|null $trimester_id
 * @property string $message
 * @property array|null $motives
 * @property string|null $observation
 * @property string|null $appointment_date
 * @property string|null $appointment_time
 * @property string|null $generated_date
 * @property Carbon|null $sent_at
 * @property Carbon|null $printed_at
 * @property Carbon|null $summoned_at
 * @property string|null $parent_attended
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'code', 'notification_number', 'type', 'channel', 'student_id',
    'grade_id', 'subject_id', 'teacher_id', 'year_id', 'trimester_id',
    'message', 'motives', 'observation', 'appointment_date', 'appointment_time',
    'generated_date', 'sent_at', 'printed_at', 'summoned_at', 'parent_attended',
])]
class AcademicNotification extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'grade_id' => 'integer',
            'subject_id' => 'integer',
            'teacher_id' => 'integer',
            'year_id' => 'integer',
            'trimester_id' => 'integer',
            'motives' => 'array',
            'appointment_date' => 'date',
            'appointment_time' => 'datetime:H:i',
            'generated_date' => 'date',
            'sent_at' => 'datetime',
            'printed_at' => 'datetime',
            'summoned_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(ScolarYear::class, 'year_id');
    }

    public function trimester(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'trimester_id');
    }

    /**
     * @return HasMany<NotificationChannel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(NotificationChannel::class, 'notification_id');
    }
}
