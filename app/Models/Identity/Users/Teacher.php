<?php

namespace App\Models\Identity\Users;

use App\Models\Incidents\IncidentCommitmentLetter;
use App\Models\Incidents\IncidentIntervention;
use App\Models\Incidents\IncidentReport;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\ClassObservation;
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
 * @property string $teacher_code
 * @property string|null $specialization
 * @property string|null $hire_date
 * @property string|null $title
 * @property string|null $education_level
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'user_id', 'teacher_code', 'specialization', 'hire_date',
    'title', 'education_level',
])]
class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'hire_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function classObservationsAsTutor(): HasMany
    {
        return $this->hasMany(ClassObservation::class, 'tutor_id');
    }

    public function classObservationsAsTeacher(): HasMany
    {
        return $this->hasMany(ClassObservation::class, 'teacher_id');
    }

    public function attendancesAsTutor(): HasMany
    {
        return $this->hasMany(Attendance::class, 'tutor_id');
    }

    public function getFullNameAttribute(): string
    {
        return $this->user?->lastname.' '.$this->user?->name ?? '';
    }
}
