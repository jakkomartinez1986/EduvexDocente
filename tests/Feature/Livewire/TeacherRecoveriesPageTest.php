<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * El web (SFC de recuperaciones) debe asignar un client_uid propio a cada
 * recuperación registrada, de modo que el cliente Flutter pueda enrutarla en
 * el push offline (task 2c).
 */
it('asigna un client_uid automático al registrar recuperaciones desde el web', function (): void {
    $context = academicContext();

    $student = Student::factory()->create();

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]);

    $block = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
    ]);

    $activity = Activity::factory()->create(['assessment_block_id' => $block->id]);

    ActivityGrade::factory()->create([
        'activity_id' => $activity->id,
        'student_id' => $student->id,
        'grade' => 6.0,
        'recorded_by' => $context['teacher']->user->id,
    ]);

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.recoveries.index')
        ->assertOk()
        ->set('selectedActivityId', $activity->id)
        ->set('recoveryGrade', [$student->id => '8'])
        ->set('recoveryMethod', [$student->id => 'average'])
        ->call('registerRecovery', $student->id);

    $recovery = ActivityRecovery::where('activity_id', $activity->id)->where('student_id', $student->id)->firstOrFail();

    expect(Str::isUuid((string) $recovery->client_uid))->toBeTrue();
});
