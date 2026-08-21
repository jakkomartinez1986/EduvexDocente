<?php

namespace App\Models\Incidents;

use App\Models\Identity\Users\Representative;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncidentCommitmentLetter extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'sequential_number', 'type',
        'student_id', 'grade_id', 'subject_id', 'teacher_id',
        'representative_id', 'year_id', 'trimester_id',
        'date', 'commitments', 'status', 'signed_at', 'signatures',
    ];

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'grade_id' => 'integer',
            'subject_id' => 'integer',
            'teacher_id' => 'integer',
            'representative_id' => 'integer',
            'year_id' => 'integer',
            'trimester_id' => 'integer',
            'date' => 'date',
            'signed_at' => 'date',
            'signatures' => 'array',
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

    public function representative(): BelongsTo
    {
        return $this->belongsTo(Representative::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(ScolarYear::class, 'year_id');
    }

    public function trimester(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'trimester_id');
    }
}
