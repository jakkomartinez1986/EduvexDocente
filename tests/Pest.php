<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Security\Authorizations\Permission;
use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\GradingScheme;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use App\Support\Api\ApiModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often want to check that values meet certain conditions. The
| expect() function gives you access to a set of expectations methods. You can extend the
| Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every test file. Here you can also expose helpers as
| global functions to help reduce the number of lines of code in your test files.
|
*/

/**
 * Cabeceras de autenticación Bearer para peticiones a la API en pruebas.
 *
 * El guard RequestGuard de Sanctum cachea al usuario resuelto y la instancia
 * del guard persiste entre peticiones dentro de un mismo test; sin
 * forgetGuards(), una segunda petición con el token de OTRO usuario seguiría
 * autenticada como el usuario anterior.
 *
 * @return array<string, string>
 */
function bearerTokenFor(User $user): array
{
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('testing')->plainTextToken];
}

/**
 * Token con abilities arbitrarias (para probar el gate token.ability).
 *
 * @param  array<int, string>  $abilities
 * @return array<string, string>
 */
function bearerTokenWithAbilities(User $user, array $abilities): array
{
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('testing', $abilities)->plainTextToken];
}

/**
 * Otorga al usuario permisos Spatie que habilitan los módulos de la API
 * indicados (schedule / attendance / grades). Un permiso por módulo es
 * suficiente: el gate revisa la columna `module`.
 *
 * @param  array<int, string>|null  $modules  null = los tres módulos.
 */
function attachApiModulePermissions(User $user, ?array $modules = null): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($modules ?? array_keys(ApiModules::PERMISSION_MODELS) as $module) {
        $model = ApiModules::PERMISSION_MODELS[$module][0];
        $name = 'ver-'.strtolower($model);

        Permission::firstOrCreate([
            'name' => $name,
            'label' => 'Ver '.$model,
            'module' => $model,
            'guard_name' => config('auth.defaults.guard'),
        ]);

        $user->givePermissionTo($name);
    }
}

/**
 * Contexto académico coherente para pruebas de la API v1: institución
 * activa, año lectivo activo, período con ventana de calificación abierta,
 * esquema de calificación (80/0/14/6), estructura jornada-nivel-grado,
 * asignatura y un horario OFFICIAL del docente dado. El docente recibe
 * permisos de los tres módulos para que sus tokens incluyan las abilities.
 *
 * @param  Teacher|null  $teacher  Docente propietario del horario; se crea uno si es null.
 * @return array<string, mixed>
 */
function academicContext(?Teacher $teacher = null): array
{
    $school = School::factory()->create();
    $teacher ??= Teacher::factory()->create();

    attachApiModulePermissions($teacher->user);

    $year = ScolarYear::factory()->active()->create([
        'year_name' => '2026',
        'start_date' => now()->subDays(60)->toDateString(),
        'end_date' => now()->addMonths(8)->toDateString(),
    ]);

    $trimester = AcademicPeriod::factory()->create([
        'year_id' => $year->id,
        'trimester_name' => 'Primer Trimestre',
    ]);

    $scheme = GradingScheme::factory()->create([
        'year_id' => $year->id,
        'formative_percentage' => 80.0,
        'summative_percentage' => 0.0,
        'exam_percentage' => 14.0,
        'project_percentage' => 6.0,
    ]);

    $shift = Shift::factory()->create(['shift_name' => 'Matutina']);
    $nivel = Nivel::factory()->create(['shift_id' => $shift->id]);
    $grade = Grade::factory()->create(['nivel_id' => $nivel->id]);
    $area = Area::factory()->create(['area_name' => 'Matemáticas']);
    $subject = Subject::factory()->create(['area_id' => $area->id]);

    $schedule = ClassSchedule::factory()->create([
        'year_id' => $year->id,
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'grade_id' => $grade->id,
        'day' => 'LUNES',
        'start_time' => '07:00',
        'end_time' => '08:00',
    ]);

    return [
        'school' => $school,
        'teacher' => $teacher,
        'year' => $year,
        'trimester' => $trimester,
        'scheme' => $scheme,
        'shift' => $shift,
        'nivel' => $nivel,
        'grade' => $grade,
        'area' => $area,
        'subject' => $subject,
        'schedule' => $schedule,
    ];
}

/**
 * Contexto de sync: academicContext + 3 estudiantes matriculados en el grado.
 *
 * @return array<string, mixed>
 */
function syncContext(): array
{
    $context = academicContext();

    $students = Student::factory()->count(3)->create();
    $students->each(fn (Student $student) => StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]));

    return [...$context, 'students' => $students];
}

/**
 * Contexto de sync con estructura de gradebook (block + activity) del docente.
 *
 * @return array<string, mixed>
 */
function syncGradebookContext(): array
{
    $context = syncContext();

    $block = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
    ]);

    $activity = Activity::factory()->create([
        'assessment_block_id' => $block->id,
        'name' => 'Actividad 1',
        'max_score' => 10,
    ]);

    return [...$context, 'block' => $block, 'activity' => $activity];
}

/**
 * Lote de push con una sola operación lista para POST /sync/push.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function pushPayload(string $entity, string $action, array $payload): array
{
    return [
        'device_id' => (string) Str::uuid(),
        'operations' => [
            [
                'operation_id' => (string) Str::uuid(),
                'entity' => $entity,
                'action' => $action,
                'client_updated_at' => now()->toISOString(),
                'payload' => $payload,
            ],
        ],
    ];
}

/**
 * URL del endpoint pull con collections/cursor opcionales.
 */
function pullUrl(string $collections = 'attendance,gradebook', ?string $cursor = null): string
{
    return '/api/v1/sync/pull?'.http_build_query(array_filter([
        'collections' => $collections,
        'cursor' => $cursor,
    ]));
}
