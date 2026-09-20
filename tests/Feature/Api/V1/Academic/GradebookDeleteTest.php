<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Sync\SyncTombstone;

/**
 * P0-2 (API_ROADMAP §7): borrado soft de bloques y actividades vía REST.
 * El GradebookTombstoneObserver elimina en cascada la estructura y publica
 * tombstones (assessment_block / activity / activity_grade) para el pull.
 */
it('elimina una actividad por API con tombstones de notas', function (): void {
    $context = syncGradebookContext();
    [$a, $b] = $context['students'];
    $teacherUser = $context['teacher']->user;

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'grade' => 8.0,
        'recorded_by' => $teacherUser->id,
    ]);
    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $b->id,
        'grade' => 7.0,
        'recorded_by' => $teacherUser->id,
    ]);

    $response = $this->deleteJson('/api/v1/grades/activities/'.$context['activity']->id, [], bearerTokenFor($teacherUser));

    $response->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect(Activity::find($context['activity']->id))->toBeNull();
    expect(Activity::withTrashed()->find($context['activity']->id))->not->toBeNull();
    expect(ActivityGrade::query()->count())->toBe(0);
    expect(ActivityGrade::withTrashed()->count())->toBe(2);

    expect(SyncTombstone::query()->where('entity', 'activity')->where('entity_id', $context['activity']->id)->count())->toBe(1);
    expect(SyncTombstone::query()->where('entity', 'activity_grade')->count())->toBe(2);
});

it('elimina un bloque con su cascada y publica los tombstones de la estructura', function (): void {
    $context = syncGradebookContext();
    $teacherUser = $context['teacher']->user;

    $secondActivity = Activity::factory()->create([
        'assessment_block_id' => $context['block']->id,
        'name' => 'Actividad 2',
    ]);

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $context['students'][0]->id,
        'grade' => 9.0,
        'recorded_by' => $teacherUser->id,
    ]);

    $response = $this->deleteJson('/api/v1/grades/blocks/'.$context['block']->id, [], bearerTokenFor($teacherUser));

    $response->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect(AssessmentBlock::withTrashed()->find($context['block']->id))->not->toBeNull();
    expect(Activity::withTrashed()->whereIn('id', [$context['activity']->id, $secondActivity->id])->count())->toBe(2);
    expect(ActivityGrade::withTrashed()->count())->toBe(1);

    expect(SyncTombstone::query()->where('entity', 'assessment_block')->where('entity_id', $context['block']->id)->count())->toBe(1);
    expect(SyncTombstone::query()->where('entity', 'activity')->count())->toBe(2);
    expect(SyncTombstone::query()->where('entity', 'activity_grade')->count())->toBe(1);
});

it('entrega tombstones de estructura en el pull de gradebook tras borrar', function (): void {
    $context = syncGradebookContext();
    $teacherUser = $context['teacher']->user;
    $headers = bearerTokenFor($teacherUser);

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $context['students'][0]->id,
        'grade' => 8.0,
        'recorded_by' => $teacherUser->id,
    ]);

    $this->deleteJson('/api/v1/grades/activities/'.$context['activity']->id, [], $headers)->assertOk();

    $pull = $this->getJson(pullUrl('gradebook'), $headers)
        ->assertOk();

    $tombstones = $pull->json('data.changes.gradebook.tombstones');

    expect(collect($tombstones)->contains(
        fn (array $tombstone): bool => $tombstone['entity'] === 'activity' && (int) $tombstone['id'] === $context['activity']->id,
    ))->toBeTrue();

    expect(collect($tombstones)->contains(
        fn (array $tombstone): bool => $tombstone['entity'] === 'assessment_block',
    ))->toBeFalse();
});

it('rechaza borrar bloques y actividades de otro docente con 404', function (): void {
    $context = syncGradebookContext();

    $foreignBlock = AssessmentBlock::factory()->create();
    $foreignActivity = Activity::factory()->create(['assessment_block_id' => $foreignBlock->id]);

    $headers = bearerTokenFor($context['teacher']->user);

    $this->deleteJson('/api/v1/grades/blocks/'.$foreignBlock->id, [], $headers)->assertStatus(404);
    $this->deleteJson('/api/v1/grades/activities/'.$foreignActivity->id, [], $headers)->assertStatus(404);

    expect(AssessmentBlock::find($foreignBlock->id))->not->toBeNull();
    expect(SyncTombstone::query()->count())->toBe(0);
});

it('devuelve 404 al borrar estructuras inexistentes', function (): void {
    $context = syncGradebookContext();
    $headers = bearerTokenFor($context['teacher']->user);

    $this->deleteJson('/api/v1/grades/blocks/999999', [], $headers)->assertStatus(404);
    $this->deleteJson('/api/v1/grades/activities/999999', [], $headers)->assertStatus(404);
});
