<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Academic\GradeBook\Summaries\Supplementary\SupplementaryExam;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\User;

function downloadUrl(array $filters = []): string
{
    return '/api/v1/academic/gradebook/download'.($filters !== [] ? '?'.http_build_query($filters) : '');
}

function gradebookDownloadContext(): array
{
    $context = academicContext();

    $student = Student::factory()->create();

    $block = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'name' => 'Bloque 1',
    ]);

    $activity = Activity::factory()->create(['assessment_block_id' => $block->id]);

    ActivityGrade::factory()->create([
        'activity_id' => $activity->id,
        'student_id' => $student->id,
        'grade' => 8.5,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    $exam = StudentExam::create([
        'student_id' => $student->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'grade' => 9.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    $project = StudentProject::create([
        'student_id' => $student->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'grade' => 7.5,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    $supplementary = SupplementaryExam::create([
        'student_id' => $student->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'grade' => 6.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    return [
        ...$context,
        'student' => $student,
        'block' => $block,
        'activity' => $activity,
        'exam' => $exam,
        'project' => $project,
        'supplementary' => $supplementary,
    ];
}

it('descompone el libro en bloques, exámenes, proyectos y supletorios para offline', function (): void {
    $context = gradebookDownloadContext();

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->getJson(downloadUrl(), bearerTokenFor($user));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.year_id', $context['year']->id);

    $data = $response->json('data');

    expect($data)->toHaveKeys(['generated_at', 'blocks', 'exams', 'projects', 'supplementary_exams']);
    expect($data['blocks'])->toHaveCount(1)
        ->and($data['blocks'][0]['id'])->toBe($context['block']->id)
        ->and($data['blocks'][0]['activities'][0]['id'])->toBe($context['activity']->id)
        ->and((float) $data['blocks'][0]['activities'][0]['grades'][0]['grade'])->toBe(8.5);

    expect($data['exams'])->toHaveCount(1)
        ->and((float) $data['exams'][0]['grade'])->toBe(9.0);

    expect($data['projects'])->toHaveCount(1)
        ->and((float) $data['projects'][0]['grade'])->toBe(7.5);

    expect($data['supplementary_exams'])->toHaveCount(1)
        ->and((float) $data['supplementary_exams'][0]['grade'])->toBe(6.0);
});

it('respeta los filtros de asignatura, grado y trimestre', function (): void {
    $context = gradebookDownloadContext();

    $otherSubject = Subject::factory()->create();
    AssessmentBlock::factory()->create([
        'subject_id' => $otherSubject->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->getJson(
        downloadUrl([
            'subject_id' => $context['subject']->id,
            'grade_id' => $context['grade']->id,
            'trimester_id' => $context['trimester']->id,
        ]),
        bearerTokenFor($user),
    )->assertOk()
        ->assertJsonCount(1, 'data.blocks')
        ->assertJsonPath('data.blocks.0.id', $context['block']->id);
});

it('no expone bloques ni notas de otros docentes', function (): void {
    $context = gradebookDownloadContext();

    $otherTeacher = Teacher::factory()->create();
    AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $otherTeacher->id,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->getJson(downloadUrl(['year_id' => $context['year']->id]), bearerTokenFor($user))
        ->assertOk()
        ->assertJsonCount(1, 'data.blocks')
        ->assertJsonPath('data.blocks.0.teacher_id', $context['teacher']->id);
});

it('devuelve un dataset vacío cuando no hay asignación en el año indicado', function (): void {
    $context = academicContext();
    $idleYear = ScolarYear::factory()->create(['year_name' => '2030']);

    $otherGrade = Grade::factory()->create();
    AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $otherGrade->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $idleYear->id,
        'teacher_id' => Teacher::factory()->create()->id,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->getJson(downloadUrl(['year_id' => $idleYear->id]), bearerTokenFor($user))
        ->assertOk()
        ->assertJsonPath('data.year_id', $idleYear->id)
        ->assertJsonPath('data.blocks', [])
        ->assertJsonPath('data.exams', [])
        ->assertJsonPath('data.projects', [])
        ->assertJsonPath('data.supplementary_exams', []);
});

it('requiere la ability grades.read', function (): void {
    $context = academicContext();

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->getJson(downloadUrl(), bearerTokenWithAbilities($user, ['auth.me']))
        ->assertStatus(403);
});
