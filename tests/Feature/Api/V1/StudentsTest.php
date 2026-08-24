<?php

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use Illuminate\Auth\AuthManager;

/**
 * Fase 3 - Configuration consolidada:
 * - D-06/H-04: los estudiantes ya no viajan dentro del bootstrap y se exponen
 *   por un endpoint propio con DTO mínimo (§3.2).
 * - D-07: paginación por cursor (?cursor= + meta.next_cursor).
 * - Autorización por propiedad: solo estudiantes de grados asignados.
 */
function enrolledStudentIn(Grade $grade, ScolarYear $year): Student
{
    $student = Student::factory()->create();

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $grade->id,
        'year_id' => $year->id,
        'status' => 'active',
        'academic_year' => $year->year_name,
    ]);

    return $student;
}

it('entrega el dto minimo de estudiante sin datos sensibles (H-04)', function (): void {
    $context = academicContext();
    $student = enrolledStudentIn($context['grade'], $context['year']);

    $response = $this->get('/api/v1/students', bearerTokenFor($context['teacher']->user));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('meta.next_cursor', null);

    $first = $response->json('data.0');

    expect(array_keys($first))->toEqual([
        'id', 'student_code', 'name', 'lastname', 'full_name',
        'grade_id', 'enrollment_status', 'profile_photo_url',
    ])
        ->and($first['id'])->toBe($student->id)
        ->and($first['grade_id'])->toBe($context['grade']->id)
        ->and($first['enrollment_status'])->toBe('active');

    foreach (['email', 'dni', 'roles', 'permissions', 'birth_date', 'blood_type', 'medical_info'] as $forbidden) {
        expect($first)->not->toHaveKey($forbidden);
    }
});

it('excluye estudiantes de grados no asignados al docente', function (): void {
    $context = academicContext();

    $otherShift = Shift::factory()->create();
    $otherNivel = Nivel::factory()->create(['shift_id' => $otherShift->id]);
    $otherGrade = Grade::factory()->create(['nivel_id' => $otherNivel->id]);
    enrolledStudentIn($context['grade'], $context['year']);
    enrolledStudentIn($otherGrade, $context['year']);

    $response = $this->get('/api/v1/students', bearerTokenFor($context['teacher']->user));

    $response->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.grade_id'))->toBe($context['grade']->id);
});

it('filtra por grade_id asignado y rechaza grados ajenos con 404', function (): void {
    $context = academicContext();

    $otherShift = Shift::factory()->create();
    $otherNivel = Nivel::factory()->create(['shift_id' => $otherShift->id]);
    $secondGrade = Grade::factory()->create(['nivel_id' => $otherNivel->id]);

    // Segundo grado TAMBIÉN asignado vía horario.
    ClassSchedule::factory()->create([
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $secondGrade->id,
        'day' => 'MARTES',
    ]);

    $inFirst = enrolledStudentIn($context['grade'], $context['year']);
    $inSecond = enrolledStudentIn($secondGrade, $context['year']);
    $headers = bearerTokenFor($context['teacher']->user);

    $this->get("/api/v1/students?grade_id={$secondGrade->id}", $headers)
        ->assertOk()
        ->assertJsonPath('data.0.id', $inSecond->id);

    expect($inFirst)->not->toBeNull();

    // Grado sin asignación para este docente → 404, nunca fuga de datos.
    // Se elige uno cuyo id no coincida con ningún grado asignado (los ids
    // son compartidos entre tablas y podrían colisionar numéricamente).
    $assignedGradeIds = ClassSchedule::query()
        ->where('teacher_id', $context['teacher']->id)
        ->where('year_id', $context['year']->id)
        ->pluck('grade_id')
        ->unique();

    do {
        $unassignedGrade = Grade::factory()->create(['nivel_id' => $otherNivel->id]);
    } while ($assignedGradeIds->contains($unassignedGrade->id));

    $this->get("/api/v1/students?grade_id={$unassignedGrade->id}", $headers)
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('pagina por cursor sin duplicados (D-07)', function (): void {
    config()->set('api.students.page_size', 2);

    $context = academicContext();
    $ids = collect([
        enrolledStudentIn($context['grade'], $context['year'])->id,
        enrolledStudentIn($context['grade'], $context['year'])->id,
        enrolledStudentIn($context['grade'], $context['year'])->id,
    ])->sort()->values();
    $headers = bearerTokenFor($context['teacher']->user);

    $firstPage = $this->get('/api/v1/students', $headers);

    $firstPage->assertOk()
        ->assertJsonPath('meta.has_more', true);

    $nextCursor = $firstPage->json('meta.next_cursor');
    expect($firstPage->json('data'))->toHaveCount(2)
        ->and(collect($firstPage->json('data'))->pluck('id')->all())->toEqual([$ids[0], $ids[1]])
        ->and($nextCursor)->not->toBeNull();

    $secondPage = $this->get('/api/v1/students?cursor='.urlencode((string) $nextCursor), $headers);

    $secondPage->assertOk()
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('meta.next_cursor', null);

    expect($secondPage->json('data'))->toHaveCount(1)
        ->and($secondPage->json('data.0.id'))->toBe($ids[2]);
});

it('requiere la ability students.read', function (): void {
    $context = academicContext();

    $this->get('/api/v1/students', bearerTokenWithAbilities($context['teacher']->user, ['auth.me']))
        ->assertForbidden()
        ->assertJsonPath('meta.code', 'insufficient_abilities')
        ->assertJsonPath('meta.required_abilities', ['students.read']);
});

it('rechaza usuarios sin perfil docente', function (): void {
    academicContext();
    $headers = bearerTokenFor(User::factory()->create());

    $this->get('/api/v1/students', $headers)
        ->assertForbidden()
        ->assertJsonPath('message', 'El usuario autenticado no tiene un perfil de docente.');
});

it('bloquea /students cuando must_change_password esta pendiente', function (): void {
    $context = academicContext();
    $context['teacher']->user->forceFill(['must_change_password' => true])->save();

    $this->get('/api/v1/students', bearerTokenFor($context['teacher']->user))
        ->assertForbidden()
        ->assertJsonPath('meta.code', 'password_change_required');
});

it('ya no incrusta estudiantes ni datos sensibles en settings/bootstrap (D-06)', function (): void {
    $context = academicContext();
    $student = enrolledStudentIn($context['grade'], $context['year']);

    // El guard de Sanctum cachea usuarios entre requests en pruebas.
    $this->app->make(AuthManager::class)->forgetGuards();

    $response = $this->get('/api/v1/settings/bootstrap', bearerTokenFor($context['teacher']->user));

    $response->assertOk();

    expect($response->json('data'))->not->toHaveKey('students')
        ->and($response->getContent())->not->toContain((string) $student->user->email)
        ->and($response->getContent())->not->toContain((string) $student->user->dni);
});
