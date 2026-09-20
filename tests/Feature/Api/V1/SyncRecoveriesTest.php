<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Supplementary\ExamRecovery;
use Illuminate\Support\Str;

/**
 * Recuperaciones idempotentes por client_uid en el push (API_ROADMAP §7):
 * register/apply/delete de recuperaciones de actividad y examen, absorbiendo
 * replays del outbox sin duplicar intentos.
 */
it('registra recuperaciones offline con client_uid de forma idempotente', function (): void {
    $context = syncGradebookContext();
    [$a] = $context['students'];
    $teacherUser = $context['teacher']->user;
    $headers = bearerTokenFor($teacherUser);

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'grade' => 6.0,
        'recorded_by' => $teacherUser->id,
    ]);

    $payload = [
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'recovery_grade' => 8.0,
        'client_uid' => (string) Str::uuid(),
    ];

    $first = $this->postJson('/api/v1/sync/push', pushPayload('activity_recovery', 'register', $payload), $headers);

    $first->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.attempt_number', 1)
        ->assertJsonPath('data.results.0.echo.is_applied', false);

    $firstId = (int) $first->json('data.results.0.echo.id');
    expect(ActivityRecovery::query()->count())->toBe(1);

    $second = $this->postJson('/api/v1/sync/push', pushPayload('activity_recovery', 'register', $payload), $headers);

    $second->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.id', $firstId);

    expect(ActivityRecovery::query()->count())->toBe(1);
});

it('aplica una recuperación offline y reenviar el apply es un no-op idempotente', function (): void {
    $context = syncGradebookContext();
    [$a] = $context['students'];
    $teacherUser = $context['teacher']->user;
    $headers = bearerTokenFor($teacherUser);

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'grade' => 6.0,
        'recorded_by' => $teacherUser->id,
    ]);

    $clientUid = (string) Str::uuid();

    $registered = $this->postJson('/api/v1/sync/push', pushPayload('activity_recovery', 'register', [
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'recovery_grade' => 8.0,
        'client_uid' => $clientUid,
    ]), $headers)->json('data.results.0.echo');

    $apply = $this->postJson('/api/v1/sync/push', pushPayload('activity_recovery', 'apply', [
        'recovery_id' => $registered['id'],
    ]), $headers);

    $apply->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.is_applied', true);

    expect((float) ActivityGrade::query()
        ->where('activity_id', $context['activity']->id)
        ->where('student_id', $a->id)
        ->value('grade'))->toBe(7.0);

    $replay = $this->postJson('/api/v1/sync/push', pushPayload('activity_recovery', 'apply', [
        'recovery_id' => $registered['id'],
    ]), $headers);

    $replay->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.is_applied', true);

    expect(ActivityRecovery::query()->count())->toBe(1);
});

it('elimina una recuperación offline y reenviar el delete es no-op aceptado', function (): void {
    $context = syncGradebookContext();
    [$a] = $context['students'];
    $teacherUser = $context['teacher']->user;
    $headers = bearerTokenFor($teacherUser);

    ActivityGrade::factory()->create([
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'grade' => 6.0,
        'recorded_by' => $teacherUser->id,
    ]);

    $clientUid = (string) Str::uuid();

    $this->postJson('/api/v1/sync/push', pushPayload('activity_recovery', 'register', [
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'recovery_grade' => 9.0,
        'client_uid' => $clientUid,
    ]), $headers)->assertOk();

    $delete = $this->postJson('/api/v1/sync/push', pushPayload('activity_recovery', 'delete', [
        'client_uid' => $clientUid,
    ]), $headers);

    $delete->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.deleted', true);

    expect(ActivityRecovery::query()->count())->toBe(0);
    expect(ActivityRecovery::withTrashed()->count())->toBe(1);

    $replay = $this->postJson('/api/v1/sync/push', pushPayload('activity_recovery', 'delete', [
        'client_uid' => $clientUid,
    ]), $headers);

    $replay->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.noop', true);

    expect(ActivityRecovery::withTrashed()->count())->toBe(1);
});

it('registra y aplica recuperaciones de examen por push de forma idempotente', function (): void {
    $context = syncGradebookContext();
    [$a] = $context['students'];
    $teacherUser = $context['teacher']->user;
    $headers = bearerTokenFor($teacherUser);

    StudentExam::create([
        'student_id' => $a->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'grade' => 4.0,
        'recorded_by' => $teacherUser->id,
    ]);

    $clientUid = (string) Str::uuid();

    $payload = [
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'student_id' => $a->id,
        'recovery_grade' => 14.0,
        'client_uid' => $clientUid,
    ];

    $first = $this->postJson('/api/v1/sync/push', pushPayload('exam_recovery', 'register', $payload), $headers);

    $first->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.attempt_number', 1);

    $firstId = (int) $first->json('data.results.0.echo.id');
    expect(ExamRecovery::query()->count())->toBe(1);

    $this->postJson('/api/v1/sync/push', pushPayload('exam_recovery', 'register', $payload), $headers)
        ->assertOk()
        ->assertJsonPath('data.results.0.echo.id', $firstId);

    expect(ExamRecovery::query()->count())->toBe(1);

    $apply = $this->postJson('/api/v1/sync/push', pushPayload('exam_recovery', 'apply', [
        'client_uid' => $clientUid,
    ]), $headers);

    $apply->assertOk()
        ->assertJsonPath('data.results.0.status', 'accepted')
        ->assertJsonPath('data.results.0.echo.is_applied', true);

    expect((float) StudentExam::query()
        ->where('student_id', $a->id)
        ->where('subject_id', $context['subject']->id)
        ->value('grade'))->toBe(9.0);
});

it('rechaza registrar recuperaciones sin client_uid en el push', function (): void {
    $context = syncGradebookContext();
    [$a] = $context['students'];

    $this->postJson('/api/v1/sync/push', pushPayload('activity_recovery', 'register', [
        'activity_id' => $context['activity']->id,
        'student_id' => $a->id,
        'recovery_grade' => 8.0,
    ]), bearerTokenFor($context['teacher']->user))
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'rejected');
});
