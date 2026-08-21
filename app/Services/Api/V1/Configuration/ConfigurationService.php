<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Configuration;

use App\Http\Resources\Api\V1\Setting\AcademicPeriodResource;
use App\Http\Resources\Api\V1\Setting\GradingSchemeResource;
use App\Models\Identity\Users\Teacher;
use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use App\Services\AcademicYearService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Construye la configuración de arranque de un usuario autenticado.
 *
 * Devuelve catálogos, contexto académico e información estructural necesaria
 * para que los clientes (Android, Escritorio, Web) conozcan qué pueden hacer
 * y dentro de qué contexto. No incluye datos transaccionales (calificaciones,
 * asistencias, estudiantes, horarios completos).
 */
final class ConfigurationService
{
    /**
     * Modelos cuyos permisos (Spatie) son relevantes para cada módulo.
     * La columna `module` de la tabla permissions usa el nombre corto del modelo.
     */
    private const MODULE_MODELS = [
        'schedule' => ['ClassSchedule'],
        'attendance' => ['Attendance', 'AttendanceSummary', 'ClassObservation'],
        'grades' => [
            'AssessmentBlock', 'Activity', 'ActivityGrade', 'ActivityRecovery',
            'StudentExam', 'StudentProject', 'SupplementaryExam',
            'AcademicReinforcement', 'GraduationExam',
        ],
    ];

    private const ATTENDANCE_STATUSES = [
        ['code' => 'P', 'label' => 'Presente', 'category' => 'present'],
        ['code' => 'A', 'label' => 'Atraso', 'category' => 'late'],
        ['code' => 'I', 'label' => 'Falta injustificada', 'category' => 'unjustified'],
        ['code' => 'J', 'label' => 'Falta justificada', 'category' => 'justified'],
        ['code' => 'AI', 'label' => 'Abandono institucional', 'category' => 'abandonment'],
        ['code' => 'AA', 'label' => 'Abandono de aula', 'category' => 'abandonment'],
    ];

    private const SCHEDULE_DAYS = ['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES'];

    private const SCHEDULE_TYPES = [
        ['code' => 'OFFICIAL', 'label' => 'Oficial'],
        ['code' => 'EVALUATION', 'label' => 'Evaluacion'],
        ['code' => 'TEST', 'label' => 'Prueba'],
        ['code' => 'MAKEUP', 'label' => 'Recuperacion'],
    ];

    public function __construct(private readonly AcademicYearService $academicYearService) {}

    /**
     * Contenido de la configuración (sin versión ni marca de tiempo).
     *
     * @return array<string, mixed>
     */
    public function content(User $user): array
    {
        $year = $this->academicYearService->getActiveYear();
        $teacher = $user->teacher instanceof Teacher ? $user->teacher : null;

        $assignments = $this->assignments($teacher, $year);
        $gradeIds = $assignments->pluck('grade_id')->unique()->values()->all();
        $subjectIds = $assignments->pluck('subject_id')->unique()->values()->all();
        $permissions = $this->permissions($user);

        return [
            'institution' => $this->institution(),
            'academic_period' => $this->academicPeriod($year),
            'academic_structure' => $this->academicStructure($gradeIds, $teacher !== null),
            'subjects' => $this->subjects($subjectIds, $teacher !== null),
            'teaching_assignments' => $assignments->values()->all(),
            'teacher' => $this->teacher($teacher),
            'grading' => $this->grading($year),
            'attendance' => $this->attendance(),
            'schedule' => $this->schedule(),
            'calendar' => $this->calendar($year),
            'permissions' => $permissions,
            'modules' => $this->modules($permissions),
            'client' => $this->client(),
        ];
    }

    /**
     * Versión de la configuración: hash del contenido relevante para el usuario.
     *
     * @param  array<string, mixed>  $content
     */
    public function version(array $content): string
    {
        $json = json_encode(
            $content,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return substr(hash('sha256', (string) $json), 0, 16);
    }

    /**
     * Envuelve el contenido con la estructura versionada del contrato.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function payload(array $content, string $version): array
    {
        return [
            'schema_version' => (string) Config::get('configuration.schema_version', '1.0'),
            'configuration_version' => $version,
            'generated_at' => now()->toISOString(),
            ...$content,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function institution(): ?array
    {
        $school = School::where('status', 1)->first();

        if ($school === null) {
            return null;
        }

        return [
            'id' => $school->id,
            'name' => $school->name_school,
            'location' => $school->location,
            'logo_url' => $school->logo_path,
            'timezone' => Config::get('app.timezone'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function academicPeriod(?ScolarYear $year): ?array
    {
        if ($year === null) {
            return null;
        }

        $periods = $year->academicPeriods()
            ->where('status', 1)
            ->orderBy('start_date')
            ->get();

        return [
            'id' => $year->id,
            'year_name' => $year->year_name,
            'start_date' => Carbon::parse($year->start_date)->toDateString(),
            'end_date' => Carbon::parse($year->end_date)->toDateString(),
            'status' => (int) $year->status,
            'periods' => AcademicPeriodResource::collection($periods)->resolve(),
        ];
    }

    /**
     * Estructura académica. Para docentes se limita a los grados que imparte;
     * para el resto de usuarios se expone la estructura activa de la institución.
     *
     * @param  array<int, int>  $gradeIds
     * @return array<string, mixed>
     */
    private function academicStructure(array $gradeIds, bool $isTeacher): array
    {
        $gradeQuery = Grade::query();

        if ($isTeacher) {
            $gradeQuery->whereIn('id', $gradeIds);
        } else {
            $gradeQuery->where('status', 1);
        }

        $grades = $gradeQuery->orderBy('grade_name')->get();

        $nivels = Nivel::query()
            ->whereIn('id', $grades->pluck('nivel_id')->unique())
            ->orderBy('nivel_name')
            ->get();

        $shifts = Shift::query()
            ->whereIn('id', $nivels->pluck('shift_id')->unique())
            ->orderBy('shift_name')
            ->get();

        $shiftById = $shifts->keyBy('id');
        $nivelById = $nivels->keyBy('id');

        return [
            'shifts' => $shifts->map(fn (Shift $shift): array => [
                'id' => $shift->id,
                'shift_name' => $shift->shift_name,
                'status' => (int) $shift->status,
            ])->values()->all(),
            'levels' => $nivels->map(fn (Nivel $nivel): array => [
                'id' => $nivel->id,
                'shift_id' => $nivel->shift_id,
                'nivel_name' => $nivel->nivel_name,
                'status' => (int) $nivel->status,
            ])->values()->all(),
            'grades' => $grades->map(fn (Grade $grade): array => [
                'id' => $grade->id,
                'nivel_id' => $grade->nivel_id,
                'nivel_name' => $nivelById->get($grade->nivel_id)?->nivel_name,
                'shift_id' => $shiftById->get($nivelById->get($grade->nivel_id)?->shift_id)?->id,
                'grade_name' => $grade->grade_name,
                'section' => $grade->section,
                'status' => (int) $grade->status,
            ])->values()->all(),
        ];
    }

    /**
     * Asignaturas disponibles para el contexto del usuario.
     *
     * @param  array<int, int>  $subjectIds
     * @return array<int, array<string, mixed>>
     */
    private function subjects(array $subjectIds, bool $isTeacher): array
    {
        $subjectQuery = Subject::query();

        if ($isTeacher) {
            $subjectQuery->whereIn('id', $subjectIds);
        }

        $subjects = $subjectQuery->orderBy('subject_name')->get();

        $areaById = Area::query()
            ->whereIn('id', $subjects->pluck('area_id')->unique())
            ->get()
            ->keyBy('id');

        return $subjects->map(fn (Subject $subject): array => [
            'id' => $subject->id,
            'area_id' => $subject->area_id,
            'area_name' => $areaById->get($subject->area_id)?->area_name,
            'subject_name' => $subject->subject_name,
        ])->all();
    }

    /**
     * Asignaciones autorizadas del docente: combinaciones únicas
     * asignatura-grado extraídas de sus horarios activos del año.
     *
     * @return Collection<int, array{subject_id: int, grade_id: int}>
     */
    private function assignments(?Teacher $teacher, ?ScolarYear $year): Collection
    {
        if ($teacher === null || $year === null) {
            return collect();
        }

        return ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->where('year_id', $year->id)
            ->where('is_active', true)
            ->get(['subject_id', 'grade_id'])
            ->unique(fn (ClassSchedule $schedule): string => $schedule->subject_id.'-'.$schedule->grade_id)
            ->map(fn (ClassSchedule $schedule): array => [
                'subject_id' => $schedule->subject_id,
                'grade_id' => $schedule->grade_id,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function grading(?ScolarYear $year): array
    {
        $scheme = $year?->gradingSchemes()->where('status', 1)->first();

        $supportsRecovery = $year !== null
            && $year->academicPeriods()
                ->where('status', 1)
                ->where('is_supletorio', true)
                ->exists();

        return [
            'scheme' => $scheme !== null
                ? (new GradingSchemeResource($scheme))->resolve()
                : null,
            'supports_recovery' => (bool) $supportsRecovery,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attendance(): array
    {
        return [
            'statuses' => self::ATTENDANCE_STATUSES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schedule(): array
    {
        return [
            'days_of_week' => self::SCHEDULE_DAYS,
            'schedule_types' => self::SCHEDULE_TYPES,
        ];
    }

    /**
     * Calendario escolar del año activo: días configurados (actividades y
     * feriados) con su período asociado. Es catálogo, no dato transaccional.
     *
     * @return array<string, mixed>
     */
    private function calendar(?ScolarYear $year): array
    {
        if ($year === null) {
            return [
                'year_id' => null,
                'year_name' => null,
                'days' => [],
            ];
        }

        $days = CalendarDay::query()
            ->where('year_id', $year->id)
            ->orderBy('date')
            ->get();

        $periodById = AcademicPeriod::query()
            ->where('year_id', $year->id)
            ->get()
            ->keyBy('id');

        return [
            'year_id' => $year->id,
            'year_name' => $year->year_name,
            'days' => $days->map(function (CalendarDay $day) use ($periodById): array {
                return [
                    'id' => $day->id,
                    'date' => Carbon::parse($day->date)->toDateString(),
                    'day_name' => $day->day_name,
                    'month_name' => $day->month_name,
                    'week' => $day->week,
                    'day_number' => $day->day_number,
                    'period' => $day->period,
                    'trimester_id' => $day->trimester_id,
                    'trimester_name' => $periodById->get($day->trimester_id)?->trimester_name,
                    'activity' => $day->activity,
                    'is_holiday' => (bool) $day->is_holiday,
                ];
            })->values()->all(),
        ];
    }

    /**
     * Permisos del usuario restringidos a los módulos del cliente
     * (libro de calificaciones, asistencia, horario docente).
     *
     * @return array<string, array<int, string>>
     */
    private function permissions(User $user): array
    {
        $permissions = $user->getAllPermissions();

        $result = [];

        foreach (self::MODULE_MODELS as $module => $models) {
            $result[$module] = $permissions
                ->filter(fn ($permission): bool => in_array($permission->module ?? null, $models, true))
                ->pluck('name')
                ->sort()
                ->values()
                ->all();
        }

        return $result;
    }

    /**
     * Módulos habilitados para el usuario según sus permisos.
     *
     * @param  array<string, array<int, string>>  $permissions
     * @return array<string, bool>
     */
    private function modules(array $permissions): array
    {
        return [
            'grades' => $permissions['grades'] !== [],
            'attendance' => $permissions['attendance'] !== [],
            'schedule' => $permissions['schedule'] !== [],
        ];
    }

    /**
     * Contexto docente del usuario autenticado.
     *
     * @return array<string, mixed>
     */
    private function teacher(?Teacher $teacher): array
    {
        return [
            'is_teacher' => $teacher !== null,
            'profile' => $teacher !== null ? [
                'id' => $teacher->id,
                'teacher_code' => $teacher->teacher_code,
                'full_name' => $teacher->user?->full_name,
            ] : null,
        ];
    }

    /**
     * Capacidades que los clientes oficiales pueden ofrecer.
     *
     * @return array<string, array<int, string>>
     */
    private function client(): array
    {
        return [
            'features' => (array) Config::get('configuration.client.features', []),
        ];
    }
}
