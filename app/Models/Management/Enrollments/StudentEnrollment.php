<?php

namespace App\Models\Management\Enrollments;

use App\Models\Identity\Users\Student;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\YearSettings\ScolarYear;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $grade_id
 * @property int $year_id
 * @property string $enrollment_date
 * @property string|null $completion_date
 * @property string $status
 * @property string $academic_year
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'student_id', 'grade_id', 'year_id', 'enrollment_date',
    'completion_date', 'status', 'academic_year', 'notes',
])]
class StudentEnrollment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'grade_id' => 'integer',
            'year_id' => 'integer',
            'enrollment_date' => 'date',
            'completion_date' => 'date',
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

    public function year(): BelongsTo
    {
        return $this->belongsTo(ScolarYear::class, 'year_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
