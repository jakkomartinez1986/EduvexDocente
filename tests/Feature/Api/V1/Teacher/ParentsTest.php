<?php

use App\Models\Identity\Users\Representative;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\User;

/**
 * GET /teacher/parents — representantes de estudiantes asignados al docente.
 * Fase 3 módulo docente: los padres viajan únicamente de estudiantes que
 * pertenecen a los grados asignados al docente autenticado.
 */
function representativeFor(Student $student, array $attributes = []): Representative
{
    return Representative::factory()->create([
        'student_id' => $student->id,
        ...$attributes,
    ]);
}

it('entrega el dto minimo de representante sin datos sensibles', function (): void {
    $context = academicContext();
    $student = Student::factory()->create();
    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
    ]);
    $parent = representativeFor($student);

    $response = $this->get('/api/v1/teacher/parents', bearerTokenFor($context['teacher']->user));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 1);

    $first = $response->json('data.parents.0');

    expect($first['id'])->toBe($parent->id)
        ->and($first['relationship'])->toBe($parent->relationship)
        ->and($first['student']['id'])->toBe($student->id)
        ->and($first['user']['email'])->toBe($parent->user->email);

    foreach (['dni', 'password', 'roles', 'permissions', 'geolocation_info', 'work_phone'] as $forbidden) {
        expect($first)->not->toHaveKey($forbidden);
    }
});

it('excluye padres de estudiantes de grados no asignados al docente', function (): void {
    $context = academicContext();

    $otherShift = Shift::factory()->create();
    $otherNivel = Nivel::factory()->create(['shift_id' => $otherShift->id]);
    $otherGrade = Grade::factory()->create(['nivel_id' => $otherNivel->id]);

    $studentIn = Student::factory()->create();
    StudentEnrollment::factory()->create([
        'student_id' => $studentIn->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
    ]);
    representativeFor($studentIn);

    $otherStudent = Student::factory()->create();
    StudentEnrollment::factory()->create([
        'student_id' => $otherStudent->id,
        'grade_id' => $otherGrade->id,
        'year_id' => $context['year']->id,
    ]);
    representativeFor($otherStudent);

    $response = $this->get('/api/v1/teacher/parents', bearerTokenFor($context['teacher']->user));

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.parents.0.student.id', $studentIn->id);
});

it('filtra representantes por grade_id asignado', function (): void {
    $context = academicContext();

    $studentInGrade = Student::factory()->create();
    StudentEnrollment::factory()->create([
        'student_id' => $studentInGrade->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
    ]);
    representativeFor($studentInGrade);

    $response = $this->get(
        "/api/v1/teacher/parents?grade_id={$context['grade']->id}",
        bearerTokenFor($context['teacher']->user),
    );

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.parents.0.student.id', $studentInGrade->id);
});

it('filtra representantes por student_id', function (): void {
    $context = academicContext();

    $studentA = Student::factory()->create();
    $studentB = Student::factory()->create();
    StudentEnrollment::factory()->create([
        'student_id' => $studentA->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
    ]);
    StudentEnrollment::factory()->create([
        'student_id' => $studentB->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
    ]);
    representativeFor($studentA);
    representativeFor($studentB);

    $response = $this->get(
        "/api/v1/teacher/parents?student_id={$studentA->id}",
        bearerTokenFor($context['teacher']->user),
    );

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.parents.0.student.id', $studentA->id);
});

it('retorna lista vacia cuando no hay representantes', function (): void {
    $context = academicContext();

    $this->get('/api/v1/teacher/parents', bearerTokenFor($context['teacher']->user))
        ->assertOk()
        ->assertJsonPath('data.parents', [])
        ->assertJsonPath('meta.total', 0);
});

it('requiere la ability students.read', function (): void {
    $context = academicContext();

    $this->get('/api/v1/teacher/parents', bearerTokenWithAbilities($context['teacher']->user, ['auth.me']))
        ->assertForbidden()
        ->assertJsonPath('meta.code', 'insufficient_abilities')
        ->assertJsonPath('meta.required_abilities', ['students.read']);
});

it('rechaza usuarios sin perfil docente', function (): void {
    academicContext();

    $this->get('/api/v1/teacher/parents', bearerTokenFor(User::factory()->create()))
        ->assertForbidden()
        ->assertJsonPath('message', 'El usuario autenticado no tiene un perfil de docente.');
});
