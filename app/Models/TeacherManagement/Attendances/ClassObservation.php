<?php

namespace App\Models\TeacherManagement\Attendances;

use App\Models\Identity\Users\Teacher;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $class_schedule_id
 * @property int|null $tutor_id
 * @property int|null $teacher_id
 * @property int|null $year_id
 * @property string $observation_date
 * @property string|null $classtopic
 * @property string $observation
 * @property string|null $novedad
 * @property string|null $novedad_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'class_schedule_id', 'tutor_id', 'teacher_id', 'year_id',
    'observation_date', 'classtopic', 'observation',
    'class_observation', 'novedad', 'novedad_type',
])]
class ClassObservation extends Model
{
    use HasFactory;

    public const NOVEDAD_TYPES = [
        'Inasistencia',
        'Atraso',
        'Riesgo de abandono institucional',
        'Tareas incumplidas',
        'Desempeño en clases',
        'Bajo rendimiento académico',
        'Incumplimiento de actividades',
        'Falta de materiales',
        'Irrespeto',
        'Indisciplina',
        'Uniforme',
        'Relaciones con compañeros',
        'Uso indebido de celular',
        'Incumplimiento de normas',
        'Otra',
    ];

    protected function casts(): array
    {
        return [
            'class_schedule_id' => 'integer',
            'tutor_id' => 'integer',
            'teacher_id' => 'integer',
            'year_id' => 'integer',
            'observation_date' => 'date',
        ];
    }

    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'tutor_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(ScolarYear::class, 'year_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
