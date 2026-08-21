<?php

namespace App\Models\Academic\GradeBook\Summaries\Reinforcements;

use App\Models\Identity\Users\Student;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
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
 * @property int $student_id
 * @property int $subject_id
 * @property int $grade_id
 * @property int $trimester_id
 * @property int $year_id
 * @property int $teacher_id
 * @property int $reinforcement_number
 * @property float|null $grade
 * @property string|null $description
 * @property string|null $type
 * @property string|null $date
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'student_id', 'subject_id', 'grade_id', 'trimester_id', 'year_id',
    'teacher_id', 'reinforcement_number', 'grade', 'description',
    'type', 'date', 'recorded_by',
])]
class AcademicReinforcement extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'subject_id' => 'integer',
            'grade_id' => 'integer',
            'trimester_id' => 'integer',
            'year_id' => 'integer',
            'teacher_id' => 'integer',
            'reinforcement_number' => 'integer',
            'grade' => 'float',
            'date' => 'date',
            'recorded_by' => 'integer',
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
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
