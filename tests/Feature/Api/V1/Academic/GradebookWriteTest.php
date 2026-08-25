<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\User;

function gradebookWriteContext(): array
{
    $context = academicContext();

    $students = Student::factory()->count(2)->create();
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

    return [...$context, 'students' => $students, 'block' => $block];
}

function gradesPayload(Student $student, float|int|null $grade): array
{
    return ['student_id' => $student->id, 'grade' => $grade];
}

it('crea un bloque de evaluación para la asignación del docente', function (): void {
    $context = gradebookWriteContext();

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->postJson('/api/v1/grades/blocks', [
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'name' => 'Bloque Parcial 1',
        'order' => 1,
    ], bearerTokenFor($user));

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.block.name', 'Bloque Parcial 1');

    expect(AssessmentBlock::query()->count())->toBe(2); // bloque del contexto + creado
});

it('rechaza bloques sin asignación de enseñanza con 404', function (): void {
    $context = gradebookWriteContext();
    $otherSubject = Subject::factory()->create();

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->postJson('/api/v1/grades/blocks', [
        'subject_id' => $otherSubject->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'name' => 'Bloque Ajeno',
    ], bearerTokenFor($user))->assertStatus(404);
});

it('crea una actividad en un bloque propio y rechaza bloques ajenos', function (): void {
    $context = gradebookWriteContext();

    /** @var User $user */
    $user = $context['teacher']->user;
    $headers = bearerTokenFor($user);

    $response = $this->postJson('/api/v1/grades/activities', [
        'assessment_block_id' => $context['block']->id,
        'name' => 'Taller 1',
        'max_score' => 10,
    ], $headers);

    $response->assertStatus(201)
        ->assertJsonPath('data.activity.name', 'Taller 1');

    expect($response->json('data.activity.max_score'))->toEqual(10.0);

    $foreignBlock = AssessmentBlock::factory()->create(); // otro docente

    $this->postJson('/api/v1/grades/activities', [
        'assessment_block_id' => $foreignBlock->id,
        'name' => 'Actividad Ajena',
        'max_score' => 10,
    ], $headers)->assertStatus(404);
});

it('guarda notas de actividad de forma idempotente (updateOrCreate)', function (): void {
    $context = gradebookWriteContext();

    [$studentA, $studentB] = $context['students'];

    $activity = Activity::factory()->create(['assessment_block_id' => $context['block']->id]);

    /** @var User $user */
    $user = $context['teacher']->user;
    $headers = bearerTokenFor($user);

    $payload = ['grades' => [gradesPayload($studentA, 8), gradesPayload($studentB, null)]];

    $first = $this->putJson("/api/v1/grades/activities/{$activity->id}/grades", $payload, $headers);

    $first->assertOk()
        ->assertJsonPath('data.updated', 2);

    expect(ActivityGrade::query()->where('activity_id', $activity->id)->count())->toBe(2);

    // Reenvío del mismo lote (retry offline) no duplica; actualiza valores.
    $second = $this->putJson("/api/v1/grades/activities/{$activity->id}/grades", [
        'grades' => [gradesPayload($studentA, 9)],
    ], $headers);

    $second->assertOk()
        ->assertJsonPath('data.updated', 1);

    expect(ActivityGrade::query()->where('activity_id', $activity->id)->count())->toBe(2);

    $updated = ActivityGrade::query()
        ->where('activity_id', $activity->id)
        ->where('student_id', $studentA->id)
        ->first();

    expect((float) $updated->grade)->toBe(9.0);
    expect($updated->recorded_by)->toBe($user->id);
});

it('rechaza notas cuando el estudiante no está matriculado', function (): void {
    $context = gradebookWriteContext();

    $outsider = Student::factory()->create();
    $activity = Activity::factory()->create(['assessment_block_id' => $context['block']->id]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->putJson("/api/v1/grades/activities/{$activity->id}/grades", [
        'grades' => [gradesPayload($outsider, 7)],
    ], bearerTokenFor($user));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['grades']);
});

it('rechaza notas fuera del rango permitido', function (): void {
    $context = gradebookWriteContext();
    $activity = Activity::factory()->create(['assessment_block_id' => $context['block']->id]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->putJson("/api/v1/grades/activities/{$activity->id}/grades", [
        'grades' => [gradesPayload($context['students'][0], 12)],
    ], bearerTokenFor($user))->assertStatus(422)
        ->assertJsonValidationErrors(['grades.0.grade']);
});

it('bloquea el registro de notas cuando la ventana de calificación está cerrada', function (): void {
    $context = academicContext();

    $closedTrimester = AcademicPeriod::factory()
        ->gradingClosed()
        ->create(['year_id' => $context['year']->id]);

    $block = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $closedTrimester->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
    ]);

    $activity = Activity::factory()->create(['assessment_block_id' => $block->id]);
    $student = Student::factory()->create();

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->putJson("/api/v1/grades/activities/{$activity->id}/grades", [
        'grades' => [gradesPayload($student, 7)],
    ], bearerTokenFor($user));

    $response->assertStatus(422);

    expect($response->json('errors.period'))->not->toBeNull();
    expect(ActivityGrade::query()->count())->toBe(0);
});

it('registra notas sumativas del examen sin duplicar en reintentos', function (): void {
    $context = gradebookWriteContext();
    [$studentA] = $context['students'];

    /** @var User $user */
    $user = $context['teacher']->user;
    $headers = bearerTokenFor($user);

    $base = [
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
    ];

    $this->putJson('/api/v1/grades/exams', [
        ...$base,
        'grades' => [gradesPayload($studentA, 8.5)],
    ], $headers)->assertOk()->assertJsonPath('data.updated', 1);

    // Reintento offline con valor distinto: actualiza, no duplica.
    $this->putJson('/api/v1/grades/exams', [
        ...$base,
        'grades' => [gradesPayload($studentA, 9)],
    ], $headers)->assertOk()->assertJsonPath('data.updated', 1);

    expect(StudentExam::query()->count())->toBe(1);
    expect((float) StudentExam::query()->first()->grade)->toBe(9.0);
});

it('registra una recuperación y al aplicarla actualiza la nota final', function (): void {
    $context = gradebookWriteContext();
    [$studentA] = $context['students'];
    $teacherUser = $context['teacher']->user;

    $activity = Activity::factory()->create(['assessment_block_id' => $context['block']->id]);

    ActivityGrade::factory()->create([
        'activity_id' => $activity->id,
        'student_id' => $studentA->id,
        'grade' => 6.0,
        'recorded_by' => $teacherUser->id,
    ]);

    $headers = bearerTokenFor($teacherUser);

    $created = $this->postJson("/api/v1/grades/activities/{$activity->id}/recoveries", [
        'student_id' => $studentA->id,
        'recovery_grade' => 8.0,
        'update_method' => 'average',
    ], $headers);

    $created->assertStatus(201)
        ->assertJsonPath('data.recovery.attempt_number', 1)
        ->assertJsonPath('data.recovery.is_applied', false);

    // Promedio de 6 y 8 = 7.0
    expect($created->json('data.recovery.final_grade'))->toEqual(7.0);

    $recoveryId = $created->json('data.recovery.id');

    $this->postJson("/api/v1/grades/recoveries/{$recoveryId}/apply", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.recovery.is_applied', true);

    expect((float) ActivityGrade::query()
        ->where('activity_id', $activity->id)
        ->where('student_id', $studentA->id)
        ->value('grade'))->toBe(7.0);

    // Aplicar dos veces debe fallar.
    $this->postJson("/api/v1/grades/recoveries/{$recoveryId}/apply", [], $headers)
        ->assertStatus(422);

    expect(ActivityRecovery::query()->where('id', $recoveryId)->value('is_applied'))->toBeTrue();
});
