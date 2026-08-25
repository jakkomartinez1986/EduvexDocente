<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Supplementary\ExamRecovery;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Livewire;

const RECOVERIES_COMPONENT = 'pages::system.teachers-management.teachers.recoveries.index';

/**
 * Contexto del módulo web: docente con horario, año activo, trimestre,
 * curso/asignatura y un estudiante matriculado.
 *
 * @return array<string, mixed>
 */
function recoveriesModuleContext(): array
{
    $context = academicContext();

    $student = Student::factory()->create();
    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]);

    return [...$context, 'student' => $student];
}

/**
 * Recuperación aplicada de examen lista para el historial.
 *
 * @param  array<string, mixed>  $context
 */
function seedModuleAppliedExamRecovery(Student $student, array $context, array $overrides = []): ExamRecovery
{
    return ExamRecovery::create([
        'student_id' => $student->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'recorded_by' => $context['teacher']->user_id,
        'attempt_number' => 1,
        'original_grade' => 5.0,
        'recovery_grade' => 8.0,
        'update_method' => 'average',
        'final_grade' => 6.5,
        'is_applied' => true,
        'applied_at' => now(),
        ...$overrides,
    ]);
}

/**
 * Horario para un docente adicional sobre el mismo año lectivo.
 */
function seedModuleSchedule(Teacher $teacher, array $context, ?Subject $subject = null, ?Grade $grade = null): ClassSchedule
{
    return ClassSchedule::factory()->create([
        'year_id' => $context['year']->id,
        'teacher_id' => $teacher->id,
        'subject_id' => ($subject ?? $context['subject'])->id,
        'grade_id' => ($grade ?? $context['grade'])->id,
    ]);
}

function recoveriesRowsOf($component): Collection
{
    return collect($component->get('appliedExamRecoveries'));
}

it('el docente solo ve recuperaciones aplicadas de su asignatura aunque el estudiante tenga otras', function (): void {
    $context = recoveriesModuleContext();
    $otherTeacher = Teacher::factory()->create();
    $otherSubject = Subject::factory()->create(['area_id' => $context['area']->id]);
    seedModuleSchedule($otherTeacher, $context, subject: $otherSubject);

    // Mismo estudiante: recuperación propia (Matemática) y ajena (Inglés).
    seedModuleAppliedExamRecovery($context['student'], $context);
    seedModuleAppliedExamRecovery($context['student'], [...$context, 'subject' => $otherSubject]);

    $this->actingAs($context['teacher']->user);

    $rows = recoveriesRowsOf(Livewire::test(RECOVERIES_COMPONENT));

    expect($rows->count())->toBe(1);
    expect((int) $rows->first()['subject']['id'])->toBe($context['subject']->id);
});

it('el docente no ve la recuperación del examen en un paralelo que no dicta', function (): void {
    $context = recoveriesModuleContext();

    // Otro docente dicta LA MISMA asignatura en otro paralelo.
    $otherTeacher = Teacher::factory()->create();
    $otherGrade = Grade::factory()->create(['nivel_id' => $context['nivel']->id]);
    seedModuleSchedule($otherTeacher, $context, grade: $otherGrade);

    $foreignStudent = Student::factory()->create();
    StudentEnrollment::factory()->create([
        'student_id' => $foreignStudent->id,
        'grade_id' => $otherGrade->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]);

    seedModuleAppliedExamRecovery($context['student'], $context);
    seedModuleAppliedExamRecovery($foreignStudent, [...$context, 'grade' => $otherGrade]);

    $this->actingAs($context['teacher']->user);

    $rows = recoveriesRowsOf(Livewire::test(RECOVERIES_COMPONENT));

    expect($rows->count())->toBe(1);
    expect((int) $rows->first()['grade']['id'])->toBe($context['grade']->id);
    expect((int) $rows->first()['student']['id'])->toBe($context['student']->id);
});

it('muestra el nombre completo del estudiante en el historial aplicado', function (): void {
    $context = recoveriesModuleContext();

    $context['student']->user->forceFill(['name' => 'Carlos', 'lastname' => 'Perez'])->save();

    seedModuleAppliedExamRecovery($context['student'], $context);

    $this->actingAs($context['teacher']->user);

    $component = Livewire::test(RECOVERIES_COMPONENT)
        ->set('activeTab', 'applied');

    $fullName = 'PEREZ CARLOS';

    $row = recoveriesRowsOf($component)->first();

    expect($row['student']['user']['full_name'])->toBe($fullName);
    expect($component->html())->toContain($fullName);
});

it('excluye recuperaciones aplicadas de un año lectivo anterior', function (): void {
    $context = recoveriesModuleContext();

    $oldYear = ScolarYear::factory()->create(['year_name' => '2025', 'status' => false]);
    $oldTrimester = AcademicPeriod::factory()->create(['year_id' => $oldYear->id]);

    seedModuleAppliedExamRecovery($context['student'], [...$context, 'trimester' => $oldTrimester, 'year' => $oldYear]);
    seedModuleAppliedExamRecovery($context['student'], $context);

    $this->actingAs($context['teacher']->user);

    $rows = recoveriesRowsOf(Livewire::test(RECOVERIES_COMPONENT));

    expect($rows->count())->toBe(1);
    expect((int) $rows->first()['year_id'])->toBe($context['year']->id);
});

it('rechaza aplicar o eliminar la recuperación de examen de otro docente', function (): void {
    $context = recoveriesModuleContext();
    $otherTeacher = Teacher::factory()->create();
    $otherSubject = Subject::factory()->create(['area_id' => $context['area']->id]);
    seedModuleSchedule($otherTeacher, $context, subject: $otherSubject);

    $foreign = seedModuleAppliedExamRecovery(
        $context['student'],
        [...$context, 'teacher' => $otherTeacher, 'subject' => $otherSubject],
        ['is_applied' => false, 'applied_at' => null, 'attempt_number' => 3],
    );

    $pendingForeign = ExamRecovery::create([
        'student_id' => $context['student']->id,
        'subject_id' => $otherSubject->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'recorded_by' => $otherTeacher->user_id,
        'attempt_number' => 2,
        'original_grade' => 5.0,
        'recovery_grade' => 8.0,
        'update_method' => 'average',
        'final_grade' => 6.5,
        'is_applied' => false,
    ]);

    $this->actingAs($context['teacher']->user);

    Livewire::test(RECOVERIES_COMPONENT)
        ->call('deleteExamRecovery', $pendingForeign->id)
        ->assertHasNoErrors()
        ->call('applyExamRecovery', $foreign->id)
        ->assertHasNoErrors();

    expect(ExamRecovery::withTrashed()->whereKey($pendingForeign->id)->exists())->toBeTrue();
    expect((bool) $foreign->refresh()->is_applied)->toBeFalse();
});

it('permite aplicar una recuperación de examen propia sin alterar ajenas', function (): void {
    $context = recoveriesModuleContext();

    StudentExam::create([
        'student_id' => $context['student']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'grade' => 5.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    $ownPending = ExamRecovery::create([
        'student_id' => $context['student']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'recorded_by' => $context['teacher']->user_id,
        'attempt_number' => 1,
        'original_grade' => 5.0,
        'recovery_grade' => 8.0,
        'update_method' => 'average',
        'final_grade' => 6.5,
        'is_applied' => false,
    ]);

    $otherTeacher = Teacher::factory()->create();
    $otherSubject = Subject::factory()->create(['area_id' => $context['area']->id]);
    seedModuleSchedule($otherTeacher, $context, subject: $otherSubject);

    $foreignPending = ExamRecovery::create([
        'student_id' => $context['student']->id,
        'subject_id' => $otherSubject->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'recorded_by' => $otherTeacher->user_id,
        'attempt_number' => 1,
        'original_grade' => 5.0,
        'recovery_grade' => 9.0,
        'update_method' => 'average',
        'final_grade' => 7.0,
        'is_applied' => false,
    ]);

    $this->actingAs($context['teacher']->user);

    Livewire::test(RECOVERIES_COMPONENT)
        ->call('applyExamRecovery', $ownPending->id)
        ->assertHasNoErrors();

    expect((bool) $ownPending->refresh()->is_applied)->toBeTrue();
    expect((bool) $foreignPending->refresh()->is_applied)->toBeFalse();

    $examGrade = StudentExam::query()
        ->where('student_id', $context['student']->id)
        ->where('subject_id', $context['subject']->id)
        ->value('grade');
    expect((float) $examGrade)->toBe(6.5);
});

it('rechaza registrar una recuperación de actividad ajena al bloque del docente', function (): void {
    $context = recoveriesModuleContext();

    $otherTeacher = Teacher::factory()->create();
    $foreignBlock = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $otherTeacher->id,
    ]);
    $foreignActivity = Activity::factory()->create([
        'assessment_block_id' => $foreignBlock->id,
        'max_score' => 10,
    ]);

    ActivityGrade::factory()->create([
        'activity_id' => $foreignActivity->id,
        'student_id' => $context['student']->id,
        'grade' => 4.0,
        'recorded_by' => $otherTeacher->user_id,
    ]);

    $this->actingAs($context['teacher']->user);

    Livewire::test(RECOVERIES_COMPONENT)
        ->set('selectedActivityId', $foreignActivity->id)
        ->set("recoveryGrade.{$context['student']->id}", 8)
        ->call('registerRecovery', $context['student']->id)
        ->assertHasNoErrors();

    expect(ActivityRecovery::count())->toBe(0);
});

it('rechaza eliminar la recuperación de actividad de otro docente', function (): void {
    $context = recoveriesModuleContext();

    $otherTeacher = Teacher::factory()->create();
    $foreignBlock = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $otherTeacher->id,
    ]);
    $foreignActivity = Activity::factory()->create(['assessment_block_id' => $foreignBlock->id]);

    $foreignRecovery = ActivityRecovery::create([
        'activity_id' => $foreignActivity->id,
        'student_id' => $context['student']->id,
        'year_id' => $context['year']->id,
        'recorded_by' => $otherTeacher->user_id,
        'attempt_number' => 1,
        'original_grade' => 4.0,
        'recovery_grade' => 6.0,
        'update_method' => 'average',
        'final_grade' => 5.0,
        'is_applied' => false,
    ]);

    $this->actingAs($context['teacher']->user);

    Livewire::test(RECOVERIES_COMPONENT)
        ->call('deleteRecovery', $foreignRecovery->id)
        ->assertHasNoErrors()
        ->call('applyRecovery', $foreignRecovery->id)
        ->assertHasNoErrors();

    expect(ActivityRecovery::withTrashed()->whereKey($foreignRecovery->id)->exists())->toBeTrue();
    expect((bool) $foreignRecovery->refresh()->is_applied)->toBeFalse();
});

it('rechaza registrar recuperación de examen sin asignación docente para la selección', function (): void {
    $context = recoveriesModuleContext();

    $unassignedSubject = Subject::factory()->create(['area_id' => $context['area']->id]);

    $this->actingAs($context['teacher']->user);

    Livewire::test(RECOVERIES_COMPONENT)
        ->set('selectedType', 'exam')
        ->set('selectedSubjectId', $unassignedSubject->id)
        ->set('selectedGradeId', $context['grade']->id)
        ->set("recoveryGrade.{$context['student']->id}", 8)
        ->call('registerExamRecovery', $context['student']->id)
        ->assertHasNoErrors();

    expect(ExamRecovery::count())->toBe(0);
});

it('el usuario autenticado sin perfil docente no ve datos', function (): void {
    $context = recoveriesModuleContext();

    seedModuleAppliedExamRecovery($context['student'], $context);

    $plainUser = User::factory()->create();
    $this->actingAs($plainUser);

    $component = Livewire::test(RECOVERIES_COMPONENT);

    expect(recoveriesRowsOf($component))->toHaveCount(0);
});
