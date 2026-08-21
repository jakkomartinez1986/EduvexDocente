<?php

namespace App\Models\Academic\GradeBook\Summaries\Subjects;

use App\Models\Identity\Users\Student;
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
 * @property float|null $grade
 * @property int $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['activity_id', 'student_id', 'grade', 'recorded_by'])]
class ActivityGrade extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'activity_id' => 'integer',
            'student_id' => 'integer',
            'grade' => 'float',
            'recorded_by' => 'integer',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
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
