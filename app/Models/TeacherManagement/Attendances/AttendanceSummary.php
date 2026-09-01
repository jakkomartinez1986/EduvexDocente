<?php

namespace App\Models\TeacherManagement\Attendances;

use App\Models\Identity\Users\Student;
use App\Models\Setting\EducationalSettings\Grade;
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
 * @property int $grade_id
 * @property int $trimester_id
 * @property int|null $year_id
 * @property int $total_classes
 * @property int $present_count
 * @property int $late_count
 * @property int $unjustified_count
 * @property int $justified_count
 * @property int $abandonment_count
 * @property int $permission_count
 * @property Carbon|null $last_updated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'student_id', 'grade_id', 'trimester_id', 'year_id',
    'total_classes', 'present_count', 'late_count',
    'unjustified_count', 'justified_count', 'abandonment_count',
    'permission_count', 'last_updated',
])]
class AttendanceSummary extends Model
{
    /** @use HasFactory<AttendanceSummaryFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'grade_id' => 'integer',
            'trimester_id' => 'integer',
            'year_id' => 'integer',
            'total_classes' => 'integer',
            'present_count' => 'integer',
            'late_count' => 'integer',
            'unjustified_count' => 'integer',
            'justified_count' => 'integer',
            'abandonment_count' => 'integer',
            'permission_count' => 'integer',
            'last_updated' => 'datetime',
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

    public function trimester(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'trimester_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(ScolarYear::class, 'year_id');
    }
}
