<?php

use App\Models\Identity\Users\Teacher;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;

function schedulesUrl(): string
{
    return '/api/v1/teachermanagement/schedules';
}

it('lista los horarios del docente autenticado en el año activo', function (): void {
    $context = academicContext();

    $extra = ClassSchedule::factory()->create([
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'day' => 'MARTES',
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->getJson(schedulesUrl(), bearerTokenFor($user));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.year_id', $context['year']->id);

    $schedules = $response->json('data.schedules');

    expect($schedules)->toHaveCount(2);
    expect(collect($schedules)->pluck('day')->all())->toEqual(['LUNES', 'MARTES']);
    expect($schedules[0]['subject']['subject_name'])->toBe($context['subject']->subject_name);
    expect($schedules[0]['start_time'])->toBe('07:00');
    expect($schedules[1]['id'])->toBe($extra->id);
});

it('no expone horarios de otros docentes', function (): void {
    $context = academicContext();

    ClassSchedule::factory()->create([
        'year_id' => $context['year']->id,
        'teacher_id' => Teacher::factory(),
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->getJson(schedulesUrl(), bearerTokenFor($user))
        ->assertOk()
        ->assertJsonCount(1, 'data.schedules');
});

it('filtra horarios por día de la semana', function (): void {
    $context = academicContext();

    ClassSchedule::factory()->create([
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'day' => 'VIERNES',
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->getJson(schedulesUrl().'?day=VIERNES', bearerTokenFor($user));

    $response->assertOk()
        ->assertJsonCount(1, 'data.schedules')
        ->assertJsonPath('data.schedules.0.day', 'VIERNES');
});

it('responde 404 cuando no existe un año lectivo activo', function (): void {
    $context = academicContext();
    $context['year']->update(['status' => 0]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->getJson(schedulesUrl(), bearerTokenFor($user))
        ->assertStatus(404)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'No existe un año lectivo activo.');
});
