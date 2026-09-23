<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Teacher;
use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Sync\SyncTombstone;
use Illuminate\Support\Str;

/**
 * Estructura del libro de calificaciones (bloques y actividades) vía push:
 * `delete` por `id` de servidor con no-op idempotente ante replays y cascada
 * soft + tombstones vía GradebookTombstoneObserver; `create` idempotente por
 * `client_uid` (auto-generado si no llega) con echo {id, client_uid}. La
 * edición sigue rechazada.
 */
it('elimina un bloque offline y publica tombstones en cascada', function (): void {
    $context = syncGradebookContext();
    $headers = bearerTokenFor($context['teacher']->user);

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $context['students'][0]->id,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    $response = $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'delete', [
        'block_id' => $context['block']->id,
    ]), $headers);

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.deleted', true)
        ->assertJsonPath('data.results.0.echo.id', $context['block']->id);

    expect(AssessmentBlock::query()->count())->toBe(0);
    expect(AssessmentBlock::withTrashed()->count())->toBe(1);

    expect(Activity::query()->count())->toBe(0);
    expect(Activity::withTrashed()->count())->toBe(1);

    expect(ActivityGrade::query()->count())->toBe(0);
    expect(ActivityGrade::withTrashed()->count())->toBe(1);

    expect(SyncTombstone::query()->where('entity', 'assessment_block')->where('entity_id', $context['block']->id)->exists())->toBeTrue();
    expect(SyncTombstone::query()->where('entity', 'activity')->where('entity_id', $context['activity']->id)->exists())->toBeTrue();
    expect(SyncTombstone::query()->where('entity', 'activity_grade')->count())->toBe(1);
});

it('elimina una actividad offline y publica su tombstone', function (): void {
    $context = syncGradebookContext();
    $headers = bearerTokenFor($context['teacher']->user);

    $response = $this->postJson('/api/v1/sync/push', pushPayload('activity', 'delete', [
        'activity_id' => $context['activity']->id,
    ]), $headers);

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.deleted', true)
        ->assertJsonPath('data.results.0.echo.id', $context['activity']->id);

    expect(Activity::query()->count())->toBe(0);
    expect(Activity::withTrashed()->count())->toBe(1);
    expect(ActivityGrade::query()->count())->toBe(0);

    expect(SyncTombstone::query()->where('entity', 'activity')->where('entity_id', $context['activity']->id)->exists())->toBeTrue();
});

it('reenviar el delete de bloque o actividad es no-op aceptado', function (): void {
    $context = syncGradebookContext();
    $headers = bearerTokenFor($context['teacher']->user);

    $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'delete', [
        'block_id' => $context['block']->id,
    ]), $headers)->assertOk();

    $replay = $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'delete', [
        'block_id' => $context['block']->id,
    ]), $headers);

    $replay->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.noop', true);

    expect(AssessmentBlock::withTrashed()->count())->toBe(1);

    $this->postJson('/api/v1/sync/push', pushPayload('activity', 'delete', [
        'activity_id' => $context['activity']->id,
    ]), $headers)->assertOk();

    $replay = $this->postJson('/api/v1/sync/push', pushPayload('activity', 'delete', [
        'activity_id' => $context['activity']->id,
    ]), $headers);

    $replay->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.noop', true);

    expect(Activity::withTrashed()->count())->toBe(1);
});

it('rechaza el delete de un bloque o actividad de otro docente', function (): void {
    $context = syncGradebookContext();

    $otherTeacher = Teacher::factory()->create();
    $otherBlock = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $otherTeacher->id,
    ]);
    $otherActivity = Activity::factory()->create([
        'assessment_block_id' => $otherBlock->id,
        'max_score' => 10,
    ]);

    $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'delete', [
        'block_id' => $otherBlock->id,
    ]), bearerTokenFor($context['teacher']->user))
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'rejected');

    $this->postJson('/api/v1/sync/push', pushPayload('activity', 'delete', [
        'activity_id' => $otherActivity->id,
    ]), bearerTokenFor($context['teacher']->user))
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'rejected');

    expect(AssessmentBlock::query()->count())->toBe(2);
    expect(Activity::query()->count())->toBe(2);
});

it('eliminar un id inexistente de bloque o actividad responde no-op aceptado', function (): void {
    $context = syncGradebookContext();
    $headers = bearerTokenFor($context['teacher']->user);

    $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'delete', [
        'block_id' => 999999,
    ]), $headers)
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.noop', true);

    $this->postJson('/api/v1/sync/push', pushPayload('activity', 'delete', [
        'activity_id' => 999999,
    ]), $headers)
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.noop', true);
});

it('exige block_id o activity_id en el delete de estructura', function (): void {
    $context = syncGradebookContext();
    $headers = bearerTokenFor($context['teacher']->user);

    $response = $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'delete', ['foo' => 1]), $headers);

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'rejected');

    expect($response->json('data.results.0.errors'))->toHaveKey('block_id');

    $response = $this->postJson('/api/v1/sync/push', pushPayload('activity', 'delete', ['foo' => 1]), $headers);

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'rejected');

    expect($response->json('data.results.0.errors'))->toHaveKey('activity_id');
});

it('crea un bloque offline y devuelve el echo con id y client_uid', function (): void {
    $context = syncGradebookContext();
    $headers = bearerTokenFor($context['teacher']->user);
    $clientUid = (string) Str::uuid();

    $response = $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'create', [
        'client_uid' => $clientUid,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'name' => 'Bloque offline',
        'internal_percentage' => 30,
        'order' => 2,
    ]), $headers);

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.client_uid', $clientUid);

    $block = AssessmentBlock::query()->where('client_uid', $clientUid)->first();

    expect($block)->not->toBeNull();
    expect($block->teacher_id)->toBe($context['teacher']->id);
    expect($block->subject_id)->toBe($context['subject']->id);
    expect($block->grade_id)->toBe($context['grade']->id);
    expect($block->trimester_id)->toBe($context['trimester']->id);
    expect($block->name)->toBe('Bloque offline');
    expect($block->internal_percentage)->toBe(30.0);
    expect($block->order)->toBe(2);
    expect($response->json('data.results.0.echo.id'))->toBe($block->id);
});

it('crea una actividad offline dentro de un bloque existente', function (): void {
    $context = syncGradebookContext();
    $headers = bearerTokenFor($context['teacher']->user);
    $clientUid = (string) Str::uuid();

    $response = $this->postJson('/api/v1/sync/push', pushPayload('activity', 'create', [
        'client_uid' => $clientUid,
        'assessment_block_id' => $context['block']->id,
        'name' => 'Taller offline',
        'max_score' => 5,
    ]), $headers);

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.assessment_block_id', $context['block']->id);

    $activity = Activity::query()->where('client_uid', $clientUid)->first();

    expect($activity)->not->toBeNull();
    expect($activity->assessment_block_id)->toBe($context['block']->id);
    expect($activity->name)->toBe('Taller offline');
    expect((float) $activity->max_score)->toBe(5.0);
});

it('reenviar el create de bloque o actividad con el mismo client_uid es idempotente', function (): void {
    $context = syncGradebookContext();
    $headers = bearerTokenFor($context['teacher']->user);

    $blockPayload = [
        'client_uid' => (string) Str::uuid(),
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'name' => 'Bloque dedupe',
    ];

    $first = $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'create', $blockPayload), $headers);
    $first->assertJsonPath('data.results.0.status', 'accepted');

    $replay = $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'create', $blockPayload), $headers);

    $replay->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.id', $first->json('data.results.0.echo.id'));

    expect(AssessmentBlock::query()->where('client_uid', $blockPayload['client_uid'])->count())->toBe(1);

    $activityPayload = [
        'client_uid' => (string) Str::uuid(),
        'assessment_block_id' => $context['block']->id,
        'name' => 'Actividad dedupe',
        'max_score' => 10,
    ];

    $firstActivity = $this->postJson('/api/v1/sync/push', pushPayload('activity', 'create', $activityPayload), $headers);
    $firstActivity->assertJsonPath('data.results.0.status', 'accepted');

    $replayActivity = $this->postJson('/api/v1/sync/push', pushPayload('activity', 'create', $activityPayload), $headers);

    $replayActivity->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.id', $firstActivity->json('data.results.0.echo.id'));

    expect(Activity::query()->where('client_uid', $activityPayload['client_uid'])->count())->toBe(1);
});

it('genera client_uid automaticamente si el create no lo incluye', function (): void {
    $context = syncGradebookContext();
    $headers = bearerTokenFor($context['teacher']->user);

    $response = $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'create', [
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'name' => 'Bloque sin uid',
    ]), $headers);

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted');

    $block = AssessmentBlock::query()->where('name', 'Bloque sin uid')->first();

    expect($block)->not->toBeNull();
    expect($block->client_uid)->not->toBeNull();
    expect($response->json('data.results.0.echo.client_uid'))->toBe($block->client_uid);
});

it('rechaza el create de un bloque fuera de la asignacion del docente', function (): void {
    $context = syncContext();
    $otherSubject = Subject::factory()->create(['area_id' => Area::factory()->create()->id]);

    $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'create', [
        'client_uid' => (string) Str::uuid(),
        'subject_id' => $otherSubject->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'name' => 'Bloque ajeno',
    ]), bearerTokenFor($context['teacher']->user))
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'rejected');
});

it('rechaza el create de una actividad sobre un bloque de otro docente', function (): void {
    $context = syncContext();
    $otherTeacher = Teacher::factory()->create();
    $otherBlock = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $otherTeacher->id,
    ]);

    $this->postJson('/api/v1/sync/push', pushPayload('activity', 'create', [
        'client_uid' => (string) Str::uuid(),
        'assessment_block_id' => $otherBlock->id,
        'name' => 'Actividad ajena',
        'max_score' => 10,
    ]), bearerTokenFor($context['teacher']->user))
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'rejected');
});

it('rechaza el create de actividad sin assessment_block_id', function (): void {
    $context = syncGradebookContext();

    $response = $this->postJson('/api/v1/sync/push', pushPayload('activity', 'create', [
        'name' => 'Huérfana',
        'max_score' => 10,
    ]), bearerTokenFor($context['teacher']->user));

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'rejected');

    expect($response->json('data.results.0.errors'))->toHaveKey('assessment_block_id');
});

it('sigue rechazando editar bloques o actividades desde sync', function (): void {
    $context = syncGradebookContext();

    $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'update', [
        'id' => $context['block']->id,
        'name' => 'Editado',
    ]), bearerTokenFor($context['teacher']->user))
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'rejected');

    $this->postJson('/api/v1/sync/push', pushPayload('activity', 'update', [
        'id' => $context['activity']->id,
        'name' => 'Editada',
    ]), bearerTokenFor($context['teacher']->user))
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'rejected');
});
