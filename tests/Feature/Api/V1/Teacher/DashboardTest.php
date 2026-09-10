<?php

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\User;

/**
 * GET /teacher/dashboard — datos agregados del docente para la pantalla principal.
 * Fase 3 módulo docente: un solo endpoint agrega perfil, período activo,
 * resumen de asignaciones y horario de hoy.
 */
it('entrega el dto del dashboard con resumen del docente', function (): void {
    $context = academicContext();

    $context['schedule']->forceFill(['day' => strtoupper(now()->isoFormat('dddd'))])->save();

    $students = Student::factory()->count(3)->create();
    $students->each(fn (Student $student) => StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
    ]));

    $response = $this->get('/api/v1/teacher/dashboard', bearerTokenFor($context['teacher']->user));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.teacher.id', $context['teacher']->id)
        ->assertJsonPath('data.academic_year.year_name', '2026')
        ->assertJsonPath('data.academic_year.current_period.id', $context['trimester']->id)
        ->assertJsonPath('data.summary.total_schedules', 1)
        ->assertJsonPath('data.summary.total_students', 3)
        ->assertJsonPath('data.summary.total_subjects', 1)
        ->assertJsonPath('data.summary.total_grades', 1)
        ->assertJsonPath('data.today_schedule.0.id', $context['schedule']->id);
});

it('computa resumen cero sin horarios asignados', function (): void {
    $context = academicContext();

    $context['schedule']->forceDelete();

    $response = $this->get('/api/v1/teacher/dashboard', bearerTokenFor($context['teacher']->user));

    $response->assertOk()
        ->assertJsonPath('data.summary.total_schedules', 0)
        ->assertJsonPath('data.summary.total_students', 0)
        ->assertJsonPath('data.summary.total_subjects', 0)
        ->assertJsonPath('data.summary.total_grades', 0)
        ->assertJsonPath('data.today_schedule', []);
});

it('requiere la ability auth.me', function (): void {
    $context = academicContext();

    $this->get('/api/v1/teacher/dashboard', bearerTokenWithAbilities($context['teacher']->user, ['students.read']))
        ->assertForbidden()
        ->assertJsonPath('meta.code', 'insufficient_abilities');
});

it('rechaza usuarios sin perfil docente', function (): void {
    academicContext();

    $this->get('/api/v1/teacher/dashboard', bearerTokenFor(User::factory()->create()))
        ->assertForbidden()
        ->assertJsonPath('message', 'El usuario autenticado no tiene un perfil de docente.');
});
