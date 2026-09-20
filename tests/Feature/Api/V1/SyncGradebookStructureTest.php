<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Teacher;
use App\Models\Sync\SyncTombstone;

/**
 * Borrado offline de estructura del libro de calificaciones (bloques y
 * actividades) vía push: solo `delete` (D-03 sigue prohibiendo crear/editar),
 * resuelto por el `id` del servidor, con no-op idempotente ante replays y
 * cascada soft + tombstones vía GradebookTombstoneObserver.
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

it('sigue rechazando crear bloques o actividades desde sync (D-03)', function (): void {
    $context = syncGradebookContext();

    $this->postJson('/api/v1/sync/push', pushPayload('assessment_block', 'create', [
        'name' => 'Bloque prohibido',
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
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
