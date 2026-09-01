<?php

use App\Models\Identity\Users\Teacher;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;

function scheduleWriteUrl(string $suffix = ''): string
{
    return '/api/v1/teachermanagement/schedules'.$suffix;
}

function schedulePayload(array $overrides = []): array
{
    $context = academicContext();

    return array_merge([
        'year_id' => $context['year']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'schedule_type' => 'OFFICIAL',
        'day' => 'MIERCOLES',
        'start_time' => '07:00',
        'end_time' => '08:00',
    ], $overrides);
}

it('crea un horario para el docente autenticado', function (): void {
    $context = academicContext();

    /** @var User $user */
    $user = $context['teacher']->user;

    $payload = schedulePayload([
        'year_id' => $context['year']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'day' => 'MIÉRCOLES',
        'classroom' => 'A-101',
    ]);

    $this->postJson(scheduleWriteUrl(), $payload, bearerTokenFor($user))
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.day', 'MIERCOLES')
        ->assertJsonPath('data.subject.id', $context['subject']->id)
        ->assertJsonPath('data.grade.id', $context['grade']->id);

    expect(ClassSchedule::query()->where('teacher_id', $context['teacher']->id)->count())->toBe(2);
});

it('normaliza miércoles inválido y exige trimestre para horas de evaluación', function (): void {
    $context = academicContext();

    /** @var User $user */
    $user = $context['teacher']->user;

    $base = schedulePayload([
        'year_id' => $context['year']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
    ]);

    // EVALUATION sin trimestre → 422.
    $this->postJson(scheduleWriteUrl(), array_merge($base, ['schedule_type' => 'EVALUATION']), bearerTokenFor($user))
        ->assertStatus(422)
        ->assertJsonValidationErrors('trimester_id');

    // Día inválido → 422.
    $this->postJson(scheduleWriteUrl(), array_merge($base, ['day' => 'DOMINGO']), bearerTokenFor($user))
        ->assertStatus(422)
        ->assertJsonValidationErrors('day');
});

it('normaliza y rechaza un segundo Acompañamiento integral en otro curso', function (): void {
    $context = academicContext();

    $aiac = Subject::factory()->create(['subject_name' => 'Acompañamiento integral en el aula']);
    $otherGrade = Grade::factory()->create();

    /** @var User $user */
    $user = $context['teacher']->user;

    $payload = schedulePayload([
        'year_id' => $context['year']->id,
        'subject_id' => $aiac->id,
        'grade_id' => $context['grade']->id,
    ]);

    $this->postJson(scheduleWriteUrl(), $payload, bearerTokenFor($user))
        ->assertStatus(201);

    // Segundo AIAC del mismo docente/año en otro grado → 409.
    $this->postJson(
        scheduleWriteUrl(),
        array_merge($payload, ['grade_id' => $otherGrade->id]),
        bearerTokenFor($user),
    )->assertStatus(409)
        ->assertJsonPath('message', 'Este docente ya tiene asignada la hora de Acompañamiento integral en otro curso. Solo se permite en un curso.');
});

it('actualiza un horario propio con reemplazo completo', function (): void {
    $context = academicContext();

    $schedule = ClassSchedule::factory()->create([
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'start_time' => '07:00',
        'end_time' => '08:00',
    ]);

    $payload = schedulePayload([
        'year_id' => $context['year']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'day' => 'VIERNES',
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->putJson(scheduleWriteUrl("/{$schedule->id}"), $payload, bearerTokenFor($user))
        ->assertOk()
        ->assertJsonPath('data.day', 'VIERNES')
        ->assertJsonPath('data.start_time', '10:00');

    expect($schedule->refresh()->end_time->format('H:i'))->toBe('11:00');
});

it('no permite leer ni modificar horarios de otros docentes', function (): void {
    $context = academicContext();

    $foreign = ClassSchedule::factory()->create([
        'year_id' => $context['year']->id,
        'teacher_id' => Teacher::factory(),
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
    ]);

    $payload = schedulePayload([
        'year_id' => $context['year']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->putJson(scheduleWriteUrl("/{$foreign->id}"), $payload, bearerTokenFor($user))
        ->assertStatus(404);

    $this->deleteJson(scheduleWriteUrl("/{$foreign->id}"), [], bearerTokenFor($user))
        ->assertStatus(404);

    expect(ClassSchedule::query()->find($foreign->id))->not->toBeNull();
});

it('elimina un horario propio (soft delete)', function (): void {
    $context = academicContext();

    $schedule = ClassSchedule::factory()->create([
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->deleteJson(scheduleWriteUrl("/{$schedule->id}"), [], bearerTokenFor($user))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(ClassSchedule::query()->find($schedule->id))->toBeNull()
        ->and(ClassSchedule::withTrashed()->find($schedule->id))->not->toBeNull();
});

it('requiere la ability schedule.write para escribir', function (): void {
    $context = academicContext();

    $payload = schedulePayload([
        'year_id' => $context['year']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->postJson(scheduleWriteUrl(), $payload, bearerTokenWithAbilities($user, ['schedule.read']))
        ->assertStatus(403);
});
