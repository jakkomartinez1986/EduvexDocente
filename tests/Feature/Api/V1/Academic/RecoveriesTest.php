<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Supplementary\ExamRecovery;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\StudentManagement\Academics\HomeworkPending;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;

function recoveriesContext(): array
{
    $context = academicContext();

    $students = Student::factory()->count(3)->create();
    $students->each(fn (Student $student) => StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]));

    $block = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
    ]);

    $activity = Activity::factory()->create(['assessment_block_id' => $block->id]);

    return [...$context, 'students' => $students, 'block' => $block, 'activity' => $activity];
}

function seedExamGrade(Student $student, array $context, float $grade): StudentExam
{
    return StudentExam::create([
        'student_id' => $student->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'grade' => $grade,
        'recorded_by' => $context['teacher']->user_id,
    ]);
}

function seedActivityGrade(Student $student, Activity $activity, float $grade, int $userId): ActivityGrade
{
    return ActivityGrade::factory()->create([
        'activity_id' => $activity->id,
        'student_id' => $student->id,
        'grade' => $grade,
        'recorded_by' => $userId,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function seedExamRecovery(Student $student, array $context, array $overrides = []): ExamRecovery
{
    return ExamRecovery::create([
        'student_id' => $student->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'recorded_by' => $context['teacher']->user_id,
        'attempt_number' => 1,
        'original_grade' => 5.0,
        'recovery_grade' => 8.0,
        'update_method' => 'average',
        'final_grade' => 6.5,
        'is_applied' => false,
        ...$overrides,
    ]);
}

/**
 * @return array{0: Teacher, 1: User}
 */
function otherRecoveriesTeacher(): array
{
    $teacher = Teacher::factory()->create();
    attachApiModulePermissions($teacher->user);

    return [$teacher, $teacher->user];
}

it('lista estudiantes recuperables de una actividad con su historial', function (): void {
    $context = recoveriesContext();
    [$low, $passing, $ungraded] = $context['students'];

    seedActivityGrade($low, $context['activity'], 4.0, $context['teacher']->user_id);
    seedActivityGrade($passing, $context['activity'], 8.0, $context['teacher']->user_id);

    ActivityRecovery::create([
        'activity_id' => $context['activity']->id,
        'student_id' => $low->id,
        'year_id' => $context['year']->id,
        'recorded_by' => $context['teacher']->user_id,
        'attempt_number' => 1,
        'original_grade' => 4.0,
        'recovery_grade' => 6.0,
        'update_method' => 'average',
        'final_grade' => 5.0,
        'is_applied' => false,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->getJson('/api/v1/recoveries/recoverable?'.http_build_query([
        'type' => 'activity',
        'activity_id' => $context['activity']->id,
    ]), bearerTokenFor($user));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.context.type', 'activity')
        ->assertJsonPath('data.context.activity.id', $context['activity']->id);

    expect((float) $response->json('data.context.passing_grade'))->toBe(7.0);

    expect(count($response->json('data.students')))->toBe(1);

    $row = $response->json('data.students.0');

    expect($row['student']['id'])->toBe($low->id);
    expect($row['current_grade'])->toEqual(4.0);
    expect($row['recovery_count'])->toBe(1);
    expect($row['recoveries'][0]['attempt_number'])->toBe(1);
    expect($row['recoveries'][0]['final_grade'])->toEqual(5.0);
    expect($response->json('data.context.counts.low'))->toBe(1);
});

it('excluye estudiantes aprobados y sin nota del listado recuperable', function (): void {
    $context = recoveriesContext();
    [$approved, $ungraded] = $context['students'];

    seedActivityGrade($approved, $context['activity'], 9.0, $context['teacher']->user_id);

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->getJson('/api/v1/recoveries/recoverable?'.http_build_query([
        'type' => 'activity',
        'activity_id' => $context['activity']->id,
    ]), bearerTokenFor($user));

    $response->assertOk();

    expect($response->json('data.students'))->toBe([]);
});

it('rechaza el listado de una actividad ajena con 404', function (): void {
    $context = recoveriesContext();
    [, $foreignUser] = otherRecoveriesTeacher();

    $this->getJson('/api/v1/recoveries/recoverable?'.http_build_query([
        'type' => 'activity',
        'activity_id' => $context['activity']->id,
    ]), bearerTokenFor($foreignUser))->assertStatus(404);
});

it('valida los parámetros del listado recuperable', function (): void {
    $context = recoveriesContext();

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->getJson('/api/v1/recoveries/recoverable', bearerTokenFor($user))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type']);

    $this->getJson('/api/v1/recoveries/recoverable?type=exam&activity_id=1', bearerTokenFor($user))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['subject_id', 'grade_id', 'trimester_id']);
});

it('lista estudiantes recuperables del examen con máximo de 20 puntos', function (): void {
    $context = recoveriesContext();
    [$low, $ok] = $context['students'];

    seedExamGrade($low, $context, 5.0);
    seedExamGrade($ok, $context, 9.0);

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->getJson('/api/v1/recoveries/recoverable?'.http_build_query([
        'type' => 'exam',
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
    ]), bearerTokenFor($user));

    $response->assertOk()
        ->assertJsonPath('data.context.type', 'exam');

    expect((float) $response->json('data.context.max_score'))->toBe(20.0);

    $rows = collect($response->json('data.students'));

    expect($rows->count())->toBe(1);
    expect($rows->first()['current_grade'])->toEqual(5.0);
});

it('rechaza el examen recuperable sin asignación docente con 404', function (): void {
    $context = recoveriesContext();
    [, $foreignUser] = otherRecoveriesTeacher();

    $this->getJson('/api/v1/recoveries/recoverable?'.http_build_query([
        'type' => 'exam',
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
    ]), bearerTokenFor($foreignUser))->assertStatus(404);
});

it('devuelve el historial de aplicadas por trimestre separando actividad y examen', function (): void {
    $context = recoveriesContext();
    [$own, , $other] = $context['students'];
    [$otherTeacher] = otherRecoveriesTeacher();

    // Aplicada del docente (incluida).
    seedExamRecovery($own, $context, ['is_applied' => true, 'applied_at' => now()]);

    // No aplicada (excluida).
    seedExamRecovery($own, $context, ['attempt_number' => 2]);

    // Bloque ajeno en el mismo trimestre (excluida).
    $foreignBlock = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $otherTeacher->id,
    ]);

    $foreignActivity = Activity::factory()->create(['assessment_block_id' => $foreignBlock->id]);

    ActivityRecovery::create([
        'activity_id' => $foreignActivity->id,
        'student_id' => $other->id,
        'year_id' => $context['year']->id,
        'recorded_by' => $otherTeacher->user_id,
        'attempt_number' => 1,
        'original_grade' => 3.0,
        'recovery_grade' => 7.0,
        'update_method' => 'average',
        'final_grade' => 5.0,
        'is_applied' => true,
        'applied_at' => now(),
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->getJson('/api/v1/recoveries/applied?'.http_build_query([
        'trimester_id' => $context['trimester']->id,
    ]), bearerTokenFor($user));

    $response->assertOk()
        ->assertJsonPath('data.trimester_id', $context['trimester']->id);

    $examRows = $response->json('data.exam_recoveries');
    $activityRows = $response->json('data.activity_recoveries');

    expect(count($examRows))->toBe(1);
    expect($examRows[0]['student']['id'])->toBe($own->id);
    expect($examRows[0]['is_applied'])->toBeTrue();
    expect(count($activityRows))->toBe(0);
});

it('excluye del historial aplicado la recuperación de examen de otro docente y asignatura', function (): void {
    $context = recoveriesContext();
    [$own] = $context['students'];
    [$otherTeacher] = otherRecoveriesTeacher();

    // Propia: incluida.
    seedExamRecovery($own, $context, ['is_applied' => true, 'applied_at' => now()]);

    // Otro docente, otra asignatura, mismo curso y trimestre: excluida.
    $otherSubject = Subject::factory()->create(['area_id' => $context['area']->id]);
    ClassSchedule::factory()->create([
        'year_id' => $context['year']->id,
        'teacher_id' => $otherTeacher->id,
        'subject_id' => $otherSubject->id,
        'grade_id' => $context['grade']->id,
    ]);

    seedExamRecovery($own, $context, [
        'subject_id' => $otherSubject->id,
        'is_applied' => true,
        'applied_at' => now(),
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $rows = collect($this->getJson('/api/v1/recoveries/applied?'.http_build_query([
        'trimester_id' => $context['trimester']->id,
    ]), bearerTokenFor($user))
        ->assertOk()
        ->json('data.exam_recoveries'));

    expect($rows->count())->toBe(1);
    expect((int) $rows->first()['subject']['id'])->toBe($context['subject']->id);
});

it('excluye la recuperación de examen del mismo docente en un curso no asignado', function (): void {
    $context = recoveriesContext();
    [$own] = $context['students'];
    [$otherTeacher] = otherRecoveriesTeacher();

    seedExamRecovery($own, $context, ['is_applied' => true, 'applied_at' => now()]);

    // Misma asignatura pero curso dictado por OTRO docente (otro paralelo): excluida.
    $foreignGrade = Grade::factory()
        ->create(['nivel_id' => $context['nivel']->id]);
    ClassSchedule::factory()->create([
        'year_id' => $context['year']->id,
        'teacher_id' => $otherTeacher->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $foreignGrade->id,
    ]);

    seedExamRecovery($own, $context, [
        'grade_id' => $foreignGrade->id,
        'is_applied' => true,
        'applied_at' => now(),
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $rows = collect($this->getJson('/api/v1/recoveries/applied?'.http_build_query([
        'trimester_id' => $context['trimester']->id,
    ]), bearerTokenFor($user))
        ->assertOk()
        ->json('data.exam_recoveries'));

    expect($rows->count())->toBe(1);
    expect((int) $rows->first()['grade']['id'])->toBe($context['grade']->id);
});

it('excluye recuperaciones de examen de un año lectivo anterior', function (): void {
    $context = recoveriesContext();
    [$own] = $context['students'];

    $oldYear = ScolarYear::factory()->create(['year_name' => '2025', 'status' => false]);
    $oldTrimester = AcademicPeriod::factory()->create(['year_id' => $oldYear->id]);

    seedExamRecovery($own, $context, [
        'trimester_id' => $oldTrimester->id,
        'year_id' => $oldYear->id,
        'is_applied' => true,
        'applied_at' => now(),
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $rows = $this->getJson('/api/v1/recoveries/applied?'.http_build_query([
        'trimester_id' => $context['trimester']->id,
    ]), bearerTokenFor($user))
        ->assertOk()
        ->json('data.exam_recoveries');

    expect($rows)->toBe([]);
});

it('incluye nombre completo del estudiante en el historial aplicado', function (): void {
    $context = recoveriesContext();
    [$own] = $context['students'];

    seedExamRecovery($own, $context, ['is_applied' => true, 'applied_at' => now()]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $row = $this->getJson('/api/v1/recoveries/applied?'.http_build_query([
        'trimester_id' => $context['trimester']->id,
    ]), bearerTokenFor($user))
        ->assertOk()
        ->json('data.exam_recoveries.0');

    expect($row['student']['user']['full_name'])
        ->toBe($own->user->lastname.' '.$own->user->name)
        ->not->toBeNull();
    expect($row['subject']['subject_name'])->not->toBeNull();
    expect($row['grade']['grade_name'])->not->toBeNull();
});

it('registra una recuperación de examen calculando final y número de intento', function (): void {
    $context = recoveriesContext();
    [$studentA] = $context['students'];
    seedExamGrade($studentA, $context, 6.0);

    /** @var User $user */
    $user = $context['teacher']->user;
    $headers = bearerTokenFor($user);

    $payload = [
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'student_id' => $studentA->id,
        'recovery_grade' => 8,
    ];

    $created = $this->postJson('/api/v1/recoveries/exams', $payload, $headers);

    $created->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.exam_recovery.attempt_number', 1)
        ->assertJsonPath('data.exam_recovery.is_applied', false);

    // Promedio de 6 y 8 = 7.0
    expect($created->json('data.exam_recovery.final_grade'))->toEqual(7.0);
    expect((int) ExamRecovery::query()->first()->recorded_by)->toBe($user->id);

    // Fuera de rango (máximo 20): rechazado por validación.
    $this->postJson('/api/v1/recoveries/exams', [...$payload, 'recovery_grade' => 25], $headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recovery_grade']);

    // Segundo intento (otro estudiante) → numeración independiente por estudiante.
    seedExamGrade($context['students'][1], $context, 6.0);

    $second = $this->postJson('/api/v1/recoveries/exams', [...$payload, 'student_id' => $context['students'][1]->id], $headers);

    $second->assertStatus(201)
        ->assertJsonPath('data.exam_recovery.attempt_number', 1);

    expect(ExamRecovery::query()->count())->toBe(2);
});

it('continúa la numeración de intentos tras eliminar recuperaciones', function (): void {
    $context = recoveriesContext();
    [$studentA] = $context['students'];
    seedExamGrade($studentA, $context, 6.0);

    /** @var User $user */
    $user = $context['teacher']->user;
    $headers = bearerTokenFor($user);

    $first = $this->postJson('/api/v1/recoveries/exams', [
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'student_id' => $studentA->id,
        'recovery_grade' => 7,
    ], $headers)->json('data.exam_recovery.id');

    $this->deleteJson("/api/v1/recoveries/exams/{$first}", [], $headers)->assertOk();

    $again = $this->postJson('/api/v1/recoveries/exams', [
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'student_id' => $studentA->id,
        'recovery_grade' => 7,
    ], $headers);

    $again->assertStatus(201)
        ->assertJsonPath('data.exam_recovery.attempt_number', 2);
});

it('rechaza la recuperación de examen sin nota previa o sin matrícula', function (): void {
    $context = recoveriesContext();
    [$enrolled, , $outsider] = $context['students'];

    /** @var User $user */
    $user = $context['teacher']->user;
    $headers = bearerTokenFor($user);

    $base = [
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
    ];

    // Matriculado pero sin nota de examen.
    $noGrade = $this->postJson('/api/v1/recoveries/exams', [...$base, 'student_id' => $enrolled->id, 'recovery_grade' => 8], $headers);

    $noGrade->assertStatus(422)
        ->assertJsonValidationErrors(['student_id']);

    // Sin matrícula en el curso.
    $notEnrolled = $this->postJson('/api/v1/recoveries/exams', [...$base, 'student_id' => $outsider->id, 'recovery_grade' => 8], $headers);

    $notEnrolled->assertStatus(422)
        ->assertJsonValidationErrors(['student_id']);
});

it('rechaza registrar recuperación de examen sin asignación docente con 404', function (): void {
    $context = recoveriesContext();
    [$studentA] = $context['students'];
    seedExamGrade($studentA, $context, 6.0);

    [, $foreignUser] = otherRecoveriesTeacher();

    $this->postJson('/api/v1/recoveries/exams', [
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'student_id' => $studentA->id,
        'recovery_grade' => 8,
    ], bearerTokenFor($foreignUser))->assertStatus(404);
});

it('aplica la recuperación del examen y sobrescribe la nota', function (): void {
    $context = recoveriesContext();
    [$studentA] = $context['students'];
    seedExamGrade($studentA, $context, 4.0);

    $recovery = seedExamRecovery($studentA, $context, ['final_grade' => 6.5]);

    /** @var User $user */
    $user = $context['teacher']->user;
    $headers = bearerTokenFor($user);

    $applied = $this->postJson("/api/v1/recoveries/exams/{$recovery->id}/apply", [], $headers);

    $applied->assertOk()
        ->assertJsonPath('data.exam_recovery.is_applied', true);

    expect((float) StudentExam::query()
        ->where('student_id', $studentA->id)
        ->where('subject_id', $context['subject']->id)
        ->value('grade'))->toBe(6.5);

    // Aplicar dos veces debe fallar.
    $this->postJson("/api/v1/recoveries/exams/{$recovery->id}/apply", [], $headers)
        ->assertStatus(422);
});

it('bloquea aplicar recuperaciones cuando la ventana de calificación está cerrada', function (): void {
    $context = recoveriesContext();
    [$studentA] = $context['students'];

    seedExamGrade($studentA, $context, 4.0);
    $recovery = seedExamRecovery($studentA, $context);

    AcademicPeriod::query()->whereKey($context['trimester']->id)->update([
        'grading_open_date' => now()->subDays(10)->toDateString(),
        'grading_close_date' => now()->subDays(5)->toDateString(),
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->postJson("/api/v1/recoveries/exams/{$recovery->id}/apply", [], bearerTokenFor($user));

    $response->assertStatus(422);

    expect($response->json('errors.period'))->not->toBeNull();
    expect((bool) $recovery->refresh()->is_applied)->toBeFalse();
});

it('permite registrar recuperaciones con la ventana cerrada pero no aplicarlas', function (): void {
    $context = recoveriesContext();
    [$studentA] = $context['students'];

    seedExamGrade($studentA, $context, 4.0);

    AcademicPeriod::query()->whereKey($context['trimester']->id)->update([
        'grading_open_date' => now()->subDays(10)->toDateString(),
        'grading_close_date' => now()->subDays(5)->toDateString(),
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $registered = $this->postJson('/api/v1/recoveries/exams', [
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'student_id' => $studentA->id,
        'recovery_grade' => 8,
    ], bearerTokenFor($user));

    $registered->assertStatus(201);
});

it('rechaza aplicar o eliminar una recuperación de examen ajena con 404', function (): void {
    $context = recoveriesContext();
    [$studentA] = $context['students'];

    $recovery = seedExamRecovery($studentA, $context);

    [, $foreignUser] = otherRecoveriesTeacher();
    $headers = bearerTokenFor($foreignUser);

    $this->postJson("/api/v1/recoveries/exams/{$recovery->id}/apply", [], $headers)->assertStatus(404);
    $this->deleteJson("/api/v1/recoveries/exams/{$recovery->id}", [], $headers)->assertStatus(404);
});

it('elimina una recuperación de examen no aplicada y rechaza aplicadas', function (): void {
    $context = recoveriesContext();
    [$studentA] = $context['students'];

    $pending = seedExamRecovery($studentA, $context);
    $appliedOne = seedExamRecovery($studentA, $context, ['attempt_number' => 2, 'is_applied' => true, 'applied_at' => now()]);

    /** @var User $user */
    $user = $context['teacher']->user;
    $headers = bearerTokenFor($user);

    $this->deleteJson("/api/v1/recoveries/exams/{$pending->id}", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect(ExamRecovery::query()->whereKey($pending->id)->count())->toBe(0);

    $this->deleteJson("/api/v1/recoveries/exams/{$appliedOne->id}", [], $headers)
        ->assertStatus(422);

    expect(ExamRecovery::query()->whereKey($appliedOne->id)->count())->toBe(1);
});

it('elimina una recuperación de actividad no aplicada y rechaza ajenas', function (): void {
    $context = recoveriesContext();
    [$studentA] = $context['students'];

    seedActivityGrade($studentA, $context['activity'], 4.0, $context['teacher']->user_id);

    $recovery = ActivityRecovery::create([
        'activity_id' => $context['activity']->id,
        'student_id' => $studentA->id,
        'year_id' => $context['year']->id,
        'recorded_by' => $context['teacher']->user_id,
        'attempt_number' => 1,
        'original_grade' => 4.0,
        'recovery_grade' => 6.0,
        'update_method' => 'average',
        'final_grade' => 5.0,
        'is_applied' => false,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;
    $headers = bearerTokenFor($user);

    $this->deleteJson("/api/v1/recoveries/activities/{$recovery->id}", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect(ActivityRecovery::query()->whereKey($recovery->id)->count())->toBe(0);

    [, $foreignUser] = otherRecoveriesTeacher();

    $other = ActivityRecovery::create([
        'activity_id' => $context['activity']->id,
        'student_id' => $studentA->id,
        'year_id' => $context['year']->id,
        'recorded_by' => $context['teacher']->user_id,
        'attempt_number' => 2,
        'original_grade' => 4.0,
        'recovery_grade' => 6.0,
        'update_method' => 'average',
        'final_grade' => 5.0,
        'is_applied' => false,
    ]);

    $resp = $this->deleteJson("/api/v1/recoveries/activities/{$other->id}", [], bearerTokenFor($foreignUser));
    $resp->assertStatus(404);
});

it('al aplicar una recuperación de actividad marca tareas pendientes como entregadas', function (): void {
    $context = recoveriesContext();
    [$studentA] = $context['students'];

    seedActivityGrade($studentA, $context['activity'], 4.0, $context['teacher']->user_id);

    $recovery = ActivityRecovery::create([
        'activity_id' => $context['activity']->id,
        'student_id' => $studentA->id,
        'year_id' => $context['year']->id,
        'recorded_by' => $context['teacher']->user_id,
        'attempt_number' => 1,
        'original_grade' => 4.0,
        'recovery_grade' => 8.0,
        'update_method' => 'average',
        'final_grade' => 6.0,
        'is_applied' => false,
    ]);

    $notified = HomeworkPending::create([
        'student_id' => $studentA->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'teacher_id' => $context['teacher']->id,
        'year_id' => $context['year']->id,
        'activity_id' => $context['activity']->id,
        'description' => 'Taller pendiente',
        'due_date' => now()->addDays(2)->toDateString(),
        'status' => 'not_submitted',
        'notified_at' => now(),
    ]);

    $fresh = HomeworkPending::create([
        'student_id' => $studentA->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'teacher_id' => $context['teacher']->id,
        'year_id' => $context['year']->id,
        'activity_id' => $context['activity']->id,
        'description' => 'Tarea sin notificar',
        'due_date' => now()->addDays(2)->toDateString(),
        'status' => 'not_submitted',
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->postJson("/api/v1/grades/recoveries/{$recovery->id}/apply", [], bearerTokenFor($user))
        ->assertOk()
        ->assertJsonPath('data.recovery.is_applied', true);

    expect($notified->refresh()->status)->toBe('not_submitted');
    expect($fresh->refresh()->status)->toBe('submitted');
});
