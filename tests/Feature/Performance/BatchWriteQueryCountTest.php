<?php

use App\Actions\TeacherManagement\SaveQuickGradesAction;
use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Services\Api\V1\TeacherManagement\AttendanceRegistrationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Mide el número de consultas de las rutas de escritura en lote (H-07).
 *
 * Las aserciones acotan el número de consultas a un rango que solo se
 * cumple con la escritura en lote (1 UPDATE con CASE para filas existentes
 * + 1 INSERT batch para nuevas) en lugar del N+1 fila a fila.
 */
function batchWriteStudents(array $context): Collection
{
    $students = Student::factory()->count(40)->create();

    $students->each(fn (Student $student) => StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]));

    return $students;
}

function countedBatchQueries(callable $operation): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $operation();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();
    DB::flushQueryLog();

    return $count;
}

it('acota las consultas al guardar notas de una actividad en lote', function (): void {
    Queue::fake();

    $context = academicContext();
    $students = batchWriteStudents($context);

    $block = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
    ]);

    $activity = Activity::factory()->create([
        'assessment_block_id' => $block->id,
        'name' => 'Actividad lote',
        'max_score' => 10,
    ]);

    $userId = $context['teacher']->user_id;

    $initial = $students->mapWithKeys(fn (Student $s): array => [$s->id => '8.0'])->all();
    $changed = $students->mapWithKeys(fn (Student $s): array => [$s->id => '9.5'])->all();

    app(SaveQuickGradesAction::class)->handle($activity->id, $initial, $userId);

    $queries = countedBatchQueries(fn () => app(SaveQuickGradesAction::class)->handle($activity->id, $changed, $userId));

    expect(ActivityGrade::query()->where('activity_id', $activity->id)->count())->toBe(40);
    expect(ActivityGrade::query()->where('activity_id', $activity->id)->where('grade', '9.5')->count())->toBe(40);

    // En lote: lectura única + UPDATE CASE + INSERT batch (5 consultas cuando
    // las 40 filas ya existen). El umbral 15 falla con el patrón fila a fila.
    expect($queries)->toBeLessThan(15);
});

it('acota las consultas al registrar asistencia con filas existentes', function (): void {
    $context = academicContext();
    $teacher = $context['teacher'];

    $students = Student::factory()->count(40)->create();
    $students->each(fn (Student $student) => StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]));

    $statuses = fn (string $status): array => $students
        ->mapWithKeys(fn (Student $s): array => [(string) $s->id => $status])
        ->all();

    $base = [
        'schedule_id' => $context['schedule']->id,
        'date' => now()->toDateString(),
        'classtopic' => 'Tema en lote',
        'statuses' => $statuses('A'),
    ];

    $service = app(AttendanceRegistrationService::class);
    $service->register($teacher, $base);

    $updated = [...$base, 'statuses' => $statuses('I')];

    $queries = countedBatchQueries(fn () => $service->register($teacher, $updated));

    // En lote: ~22 consultas incluyendo el detalle; antes del cambio eran 61
    // con 40 UPDATEs fila a fila. El umbral 35 falla con el patrón anterior.
    expect($queries)->toBeLessThan(35);
});
