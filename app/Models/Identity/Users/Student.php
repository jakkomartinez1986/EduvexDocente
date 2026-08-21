<?php

namespace App\Models\Identity\Users;

use App\Models\Academic\GradeBook\Cualitatives\CareerGuidance\CareerGuidance;
use App\Models\Academic\GradeBook\Cualitatives\ClassroomSupport\IntegralClassroomSupport;
use App\Models\Academic\GradeBook\Cualitatives\ReadingPromotion\ReadingPromotion;
use App\Models\Academic\GradeBook\Summaries\Graduation\GraduationExam;
use App\Models\Academic\GradeBook\Summaries\Reinforcements\AcademicReinforcement;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Academic\GradeBook\Summaries\Supplementary\SupplementaryExam;
use App\Models\Incidents\IncidentCommitmentLetter;
use App\Models\Incidents\IncidentIntervention;
use App\Models\Incidents\IncidentReport;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Models\StudentManagement\Academics\HomeworkPending;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\AttendanceSummary;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $student_code
 * @property string|null $enrollment_date
 * @property string|null $birth_date
 * @property string|null $blood_type
 * @property string|null $emergency_contact
 * @property string|null $medical_info
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'user_id', 'student_code', 'enrollment_date', 'birth_date',
    'blood_type', 'emergency_contact', 'medical_info',
])]
class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'enrollment_date' => 'date',
            'birth_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceSummaries(): HasMany
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function activityGrades(): HasMany
    {
        return $this->hasMany(ActivityGrade::class);
    }

    public function studentExams(): HasMany
    {
        return $this->hasMany(StudentExam::class);
    }

    public function studentProjects(): HasMany
    {
        return $this->hasMany(StudentProject::class);
    }

    public function careerGuidances(): HasMany
    {
        return $this->hasMany(CareerGuidance::class);
    }

    public function readingPromotions(): HasMany
    {
        return $this->hasMany(ReadingPromotion::class);
    }

    public function integralClassroomSupports(): HasMany
    {
        return $this->hasMany(IntegralClassroomSupport::class);
    }

    public function academicReinforcements(): HasMany
    {
        return $this->hasMany(AcademicReinforcement::class);
    }

    public function supplementaryExams(): HasMany
    {
        return $this->hasMany(SupplementaryExam::class);
    }

    public function graduationExams(): HasMany
    {
        return $this->hasMany(GraduationExam::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function representatives(): HasMany
    {
        return $this->hasMany(Representative::class);
    }

    public function homeworkPendings(): HasMany
    {
        return $this->hasMany(HomeworkPending::class);
    }

    public function academicNotifications(): HasMany
    {
        return $this->hasMany(AcademicNotification::class);
    }

    public function incidentInterventions(): HasMany
    {
        return $this->hasMany(IncidentIntervention::class);
    }

    public function incidentCommitmentLetters(): HasMany
    {
        return $this->hasMany(IncidentCommitmentLetter::class);
    }

    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->user?->lastname.' '.$this->user?->name ?? '';
    }
}
