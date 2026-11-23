<?php

use App\Jobs\RebuildAttendanceSummaries;
use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\TeacherManagement\Attendances\AttendanceSummary;
use App\Models\User;
use App\Services\Api\V1\TeacherManagement\AttendanceSummariesRebuilder;
use App\Services\TeacherManagement\AttendanceService;
use App\Services\TeacherManagement\GradebookCache;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $context
 * @return array{0: Student, 1: Student, 2: Student}
 */
function activeStudentsFixture(array $context): array
{
    $active = Student::factory()->create();
    $active->user->update(['lastname' => 'SOLOACTIVO', 'name' => 'Ana']);

    $withdrawn = Student::factory()->create();
    $withdrawn->user->update(['lastname' => 'RETIRADOXYZ', 'name' => 'Rosa']);

    $inactiveUser = User::factory()->create([
        'status' => 0,
        'lastname' => 'INACTIVOXYZ',
        'name' => 'Ivan',
    ]);
    $disabled = Student::factory()->create(['user_id' => $inactiveUser->id]);

    foreach ([$active, $withdrawn, $disabled] as $student) {
        StudentEnrollment::factory()->create([
            'student_id' => $student->id,
            'grade_id' => $context['grade']->id,
            'year_id' => $context['year']->id,
            'academic_year' => $context['year']->year_name,
        ]);
    }

    StudentEnrollment::query()
        ->where('student_id', $withdrawn->id)
        ->where('grade_id', $context['grade']->id)
        ->where('year_id', $context['year']->id)
        ->update(['status' => 'withdrawn']);

    return [$active, $withdrawn, $disabled];
}

/**
 * @param  array<string, mixed>  $context
 * @param  array<int, Student>  $students
 */
function seedLowActivityGrades(array $context, array $students): Activity
{
    $block = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
    ]);

    $activity = Activity::factory()->create(['assessment_block_id' => $block->id]);

    foreach ($students as $student) {
        ActivityGrade::factory()->create([
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'grade' => 5.0,
            'recorded_by' => $context['teacher']->user_id,
        ]);
    }

    return $activity;
}

test('el scope activo excluye matrículas retiradas y cuentas inactivas', function (): void {
    $context = academicContext();
    [$active, $withdrawn, $disabled] = activeStudentsFixture($context);

    $enrollmentStudentIds = StudentEnrollment::active()
        ->where('grade_id', $context['grade']->id)
        ->where('year_id', $context['year']->id)
        ->pluck('student_id')
        ->all();

    $studentIds = Student::activeEnrollment((int) $context['grade']->id, (int) $context['year']->id)
        ->pluck('id')
        ->all();

    expect($enrollmentStudentIds)->toBe([$active->id])
        ->and($studentIds)->toBe([$active->id])
        ->and($withdrawn->id)->not->toBe($active->id)
        ->and($disabled->id)->not->toBe($active->id);
});

test('la API del libro de calificaciones solo devuelve estudiantes activos', function (): void {
    $context = academicContext();
    [$active, $withdrawn, $disabled] = activeStudentsFixture($context);
    seedLowActivityGrades($context, [$active, $withdrawn, $disabled]);

    $response = $this->getJson(
        '/api/v1/academic/gradebook?'.http_build_query([
            'subject_id' => $context['subject']->id,
            'grade_id' => $context['grade']->id,
            'trimester_id' => $context['trimester']->id,
        ]),
        bearerTokenFor($context['teacher']->user),
    );

    $response->assertOk();

    expect(collect($response->json('data.students'))->pluck('id')->all())->toBe([$active->id]);
});

test('el libro de calificaciones web oculta retirados y cuentas inactivas', function (): void {
    $context = academicContext();
    [$active] = activeStudentsFixture($context);
    seedLowActivityGrades($context, [$active]);

    $html = Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.gradebook.index')
        ->assertOk()
        ->html();

    expect($html)->toContain('SOLOACTIVO')
        ->not->toContain('RETIRADOXYZ')
        ->not->toContain('INACTIVOXYZ');
});

test('la asistencia solo carga estudiantes activos', function (): void {
    $context = academicContext();
    [$active] = activeStudentsFixture($context);

    $service = app(AttendanceService::class);
    $scheduleId = (int) $context['schedule']->id;
    $yearId = (int) $context['year']->id;

    expect(collect($service->loadStudentsForAttendance($scheduleId, $yearId))->pluck('id')->all())
        ->toBe([$active->id])
        ->and(collect($service->loadStudentsForAttendanceCreate($scheduleId, $yearId))->pluck('student_id')->all())
        ->toBe([$active->id]);
});

test('la API de recuperaciones solo ofrece estudiantes activos', function (): void {
    $context = academicContext();
    [$active, $withdrawn, $disabled] = activeStudentsFixture($context);
    $activity = seedLowActivityGrades($context, [$active, $withdrawn, $disabled]);

    $response = $this->getJson('/api/v1/recoveries/recoverable?'.http_build_query([
        'type' => 'activity',
        'activity_id' => $activity->id,
    ]), bearerTokenFor($context['teacher']->user));

    $response->assertOk();

    expect(collect($response->json('data.students'))->pluck('student.id')->all())->toBe([$active->id]);
});

test('el cambio de estado invalida el cache del libro y reprocesa la asistencia', function (): void {
    Bus::fake([RebuildAttendanceSummaries::class]);
    Cache::flush();

    $context = academicContext();
    $student = Student::factory()->create();
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]);

    $cache = app(GradebookCache::class);
    $key = $cache->key(
        (int) $context['year']->id,
        (int) $context['subject']->id,
        (int) $context['grade']->id,
        (int) $context['teacher']->id,
        (int) $context['trimester']->id,
    );
    Cache::put($key, ['stale' => true]);

    DB::connection()->setTransactionManager(new DatabaseTransactionsManager);

    StudentEnrollment::query()->findOrFail($enrollment->id)->update(['status' => 'withdrawn']);

    $currentKey = $cache->key(
        (int) $context['year']->id,
        (int) $context['subject']->id,
        (int) $context['grade']->id,
        (int) $context['teacher']->id,
        (int) $context['trimester']->id,
    );

    expect($currentKey)->not->toBe($key)
        ->and(Cache::has($currentKey))->toBeFalse();

    Bus::assertDispatched(
        RebuildAttendanceSummaries::class,
        fn (RebuildAttendanceSummaries $job): bool => $job->yearId === (int) $context['year']->id,
    );
});

test('el rebuilder elimina resúmenes de estudiantes que dejaron de estar activos', function (): void {
    $context = academicContext();
    [$active, $withdrawn] = activeStudentsFixture($context);

    foreach ([$active, $withdrawn] as $student) {
        AttendanceSummary::create([
            'student_id' => $student->id,
            'grade_id' => $context['grade']->id,
            'trimester_id' => $context['trimester']->id,
            'year_id' => $context['year']->id,
            'total_classes' => 1,
            'present_count' => 1,
        ]);
    }

    app(AttendanceSummariesRebuilder::class)->rebuild((int) $context['year']->id);

    expect(AttendanceSummary::where('student_id', $withdrawn->id)->exists())->toBeFalse()
        ->and(AttendanceSummary::where('student_id', $active->id)->exists())->toBeTrue();
});
