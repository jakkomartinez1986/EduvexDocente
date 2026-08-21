<?php

namespace App\Models\StudentManagement\Academics;

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $subject_id
 * @property int $grade_id
 * @property int $teacher_id
 * @property int $year_id
 * @property int|null $trimester_id
 * @property int|null $activity_id
 * @property string $description
 * @property string|null $topic
 * @property string $due_date
 * @property string $status
 * @property Carbon|null $notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'student_id', 'subject_id', 'grade_id', 'teacher_id', 'year_id',
    'trimester_id', 'activity_id', 'description', 'topic', 'due_date',
    'status', 'notified_at',
])]
class HomeworkPending extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'subject_id' => 'integer',
            'grade_id' => 'integer',
            'teacher_id' => 'integer',
            'year_id' => 'integer',
            'trimester_id' => 'integer',
            'activity_id' => 'integer',
            'due_date' => 'date',
            'notified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
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

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
