<?php

use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;

it('devuelve la configuración completa de arranque con su versión', function (): void {
    $context = academicContext();

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->getJson('/api/v1/configuration', bearerTokenFor($user));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.schema_version', '1.0');

    $version = (string) $response->json('data.configuration_version');
    expect($version)->toHaveLength(16);

    $response->assertHeader('ETag', $version);

    expect($response->json('data.institution.name'))->toStartWith('Unidad Educativa ');
    expect($response->json('data.academic_period.year_name'))->toBe('2026');
    expect($response->json('data.teacher.is_teacher'))->toBeTrue();
    expect($response->json('data.teacher.profile.id'))->toBe($context['teacher']->id);
    expect($response->json('data.grading.scheme.formative_percentage'))->toEqual(80.0);
    expect($response->json('data.attendance.statuses'))->not->toBeEmpty();
    expect($response->json('data.schedule.days_of_week'))->toBeArray();

    $assignments = $response->json('data.teaching_assignments');
    expect($assignments)->toHaveCount(1);
    expect((int) $assignments[0]['subject_id'])->toBe($context['subject']->id);
    expect((int) $assignments[0]['grade_id'])->toBe($context['grade']->id);
});

it('responde sin datos cuando la versión enviada coincide con la actual', function (): void {
    $context = academicContext();

    /** @var User $user */
    $user = $context['teacher']->user;
    $headers = bearerTokenFor($user);

    $first = $this->getJson('/api/v1/configuration', $headers);
    $version = $first->json('data.configuration_version');

    $second = $this->getJson('/api/v1/configuration?version='.$version, $headers);

    $second->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', null)
        ->assertJsonPath('meta.not_modified', true)
        ->assertJsonPath('meta.configuration_version', $version)
        ->assertHeader('ETag', $version);
});

it('cambia la versión cuando cambian las asignaciones del docente', function (): void {
    $context = academicContext();

    /** @var User $user */
    $user = $context['teacher']->user;
    $headers = bearerTokenFor($user);

    $before = $this->getJson('/api/v1/configuration', $headers)
        ->json('data.configuration_version');

    ClassSchedule::factory()->create([
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'grade_id' => $context['grade']->id,
        'day' => 'MARTES',
    ]);

    $after = $this->getJson('/api/v1/configuration', $headers)
        ->json('data.configuration_version');

    expect($after)->not->toBe($before);
});

it('reporta is_teacher false para usuarios sin perfil docente', function (): void {
    academicContext();
    $plainUser = User::factory()->create();

    $response = $this->getJson('/api/v1/configuration', bearerTokenFor($plainUser));

    $response->assertOk()
        ->assertJsonPath('data.teacher.is_teacher', false)
        ->assertJsonPath('data.teacher.profile', null);
});
