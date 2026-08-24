<?php

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\User;

function enrollIn(array $context, Student $student): StudentEnrollment
{
    return StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]);
}

it('devuelve el libro con contexto, estudiantes ordenados y resumen calculado', function (): void {
    $context = academicContext();

    $alvarado = Student::factory()->create(); // user lastname Alvarado
    $benitez = Student::factory()->create();

    $alvarado->user->update(['lastname' => 'Alvarado', 'name' => 'Luis']);
    $benitez->user->update(['lastname' => 'Benitez', 'name' => 'Ana']);

    enrollIn($context, $alvarado);
    enrollIn($context, $benitez);

    /** @var AssessmentBlock $block */
    $block = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'name' => 'Bloque 1',
    ]);

    [$activityA, $activityB] = Activity::factory()->count(2)->create([
        'assessment_block_id' => $block->id,
        'max_score' => 10,
    ]);

    ActivityGrade::factory()->create([
        'activity_id' => $activityA->id,
        'student_id' => $alvarado->id,
        'grade' => 8.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);
    ActivityGrade::factory()->create([
        'activity_id' => $activityB->id,
        'student_id' => $alvarado->id,
        'grade' => 6.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);
    ActivityGrade::factory()->create([
        'activity_id' => $activityA->id,
        'student_id' => $benitez->id,
        'grade' => 9.5,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->getJson(
        '/api/v1/academic/gradebook?'.http_build_query([
            'subject_id' => $context['subject']->id,
            'grade_id' => $context['grade']->id,
            'trimester_id' => $context['trimester']->id,
        ]),
        bearerTokenFor($user),
    );

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.context.subject.id', $context['subject']->id)
        ->assertJsonPath('data.context.grade.id', $context['grade']->id)
        ->assertJsonPath('data.context.trimester.is_grading_open', true);

    expect($response->json('data.context.grading_scheme.formative_percentage'))->toEqual(80.0);

    $students = $response->json('data.students');
    // La aplicación normaliza nombres y apellidos a mayúsculas.
    expect(collect($students)->pluck('user.lastname')->all())->toEqual(['ALVARADO', 'BENITEZ']);

    // Promedio de bloque: floor((8 + 6) / 2, 2 decimales hacia abajo) = 7.0
    expect($response->json("data.summary.0.block_averages.{$block->id}"))->toEqual(7.0);
    // Formativa = promedio de bloques = 7.0; total = round(7.0 * 80 / 100, 2) = 5.6
    expect($response->json('data.summary.0.formative_average'))->toEqual(7.0);
    expect($response->json('data.summary.0.total'))->toEqual(5.6);
    expect($response->json('data.summary.0.status'))->toBe('supletorio');

    // Benitez: bloque = floor(9.5 / 2) = 4.75; total = round(4.75 * 0.8) = 3.8
    expect($response->json("data.summary.1.block_averages.{$block->id}"))->toEqual(4.75);
    expect($response->json('data.summary.1.total'))->toEqual(3.8);
    expect($response->json('data.summary.1.status'))->toBe('reprobado');

    // Las actividades incluyen las notas cargadas
    expect($response->json('data.blocks.0.activities.0.grades'))->not->toBeEmpty();
});

it('cuenta como cero las notas faltantes en el promedio del bloque', function (): void {
    $context = academicContext();
    $student = Student::factory()->create();
    enrollIn($context, $student);

    $block = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
    ]);

    $activities = Activity::factory()->count(2)->create(['assessment_block_id' => $block->id]);

    // Solo una de dos actividades tiene nota: la faltante cuenta como 0.
    ActivityGrade::factory()->create([
        'activity_id' => $activities[0]->id,
        'student_id' => $student->id,
        'grade' => 7.0,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->getJson(
        '/api/v1/academic/gradebook?'.http_build_query([
            'subject_id' => $context['subject']->id,
            'grade_id' => $context['grade']->id,
            'trimester_id' => $context['trimester']->id,
        ]),
        bearerTokenFor($user),
    );

    $response->assertOk();

    // floor(7 / 2 * 100) / 100 = 3.5
    expect($response->json("data.summary.0.block_averages.{$block->id}"))->toEqual(3.5);
});

it('excluye a estudiantes matriculados solo en otro grado', function (): void {
    $context = academicContext();

    $enrolledHere = Student::factory()->create();
    enrollIn($context, $enrolledHere);

    // Estudiante del mismo año pero de un grado distinto al consultado.
    $otherStudent = Student::factory()->create();
    $otherGrade = Grade::factory()->create();
    StudentEnrollment::factory()->create([
        'student_id' => $otherStudent->id,
        'grade_id' => $otherGrade->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->getJson(
        '/api/v1/academic/gradebook?'.http_build_query([
            'subject_id' => $context['subject']->id,
            'grade_id' => $context['grade']->id,
            'trimester_id' => $context['trimester']->id,
        ]),
        bearerTokenFor($user),
    );

    $response->assertOk()
        ->assertJsonCount(1, 'data.students')
        ->assertJsonPath('data.students.0.id', $enrolledHere->id);
});

it('responde 404 cuando el docente no tiene asignación para la asignatura', function (): void {
    $context = academicContext();
    $otherSubject = Subject::factory()->create();

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->getJson(
        '/api/v1/academic/gradebook?'.http_build_query([
            'subject_id' => $otherSubject->id,
            'grade_id' => $context['grade']->id,
            'trimester_id' => $context['trimester']->id,
        ]),
        bearerTokenFor($user),
    )->assertStatus(404)
        ->assertJsonPath('message', 'No se encontró la asignación de enseñanza para este docente.');
});

it('responde 404 cuando el trimestre es supletorio', function (): void {
    $context = academicContext();

    $supletorio = AcademicPeriod::factory()->supletorio()->create([
        'year_id' => $context['year']->id,
    ]);

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->getJson(
        '/api/v1/academic/gradebook?'.http_build_query([
            'subject_id' => $context['subject']->id,
            'grade_id' => $context['grade']->id,
            'trimester_id' => $supletorio->id,
        ]),
        bearerTokenFor($user),
    )->assertStatus(404)
        ->assertJsonPath('message', 'El período no es válido para el libro de calificaciones.');
});
