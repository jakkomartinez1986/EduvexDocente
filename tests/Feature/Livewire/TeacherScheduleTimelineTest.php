<?php

use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use Livewire\Livewire;

/**
 * Timeline de horarios (SFC de página) para usuarios sin perfil docente.
 * Verifica que un usuario sin registro en `teachers` (ej. superadmin) no
 * llegue al TypeError de ClassScheduleService y vea un mensaje amigable.
 */
it('no crashea y muestra un mensaje amigable a un usuario sin perfil de docente', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::system.teachers-management.teachers.schedules.timeline')
        ->assertOk()
        ->assertSet('isTeacher', false)
        ->assertSet('hasSchedules', false)
        ->assertSee('No eres un docente registrado');
});

it('renderiza el timeline de horarios para un docente', function (): void {
    $context = academicContext();

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.schedules.timeline')
        ->assertOk()
        ->assertSet('isTeacher', true)
        ->assertSet('hasSchedules', true);
});

it('muestra "no tienes asignaturas asignadas" a un docente sin horario en el año activo', function (): void {
    $context = academicContext();

    ClassSchedule::where('teacher_id', $context['teacher']->id)
        ->where('year_id', $context['year']->id)
        ->delete();

    Livewire::actingAs($context['teacher']->user)
        ->test('pages::system.teachers-management.teachers.schedules.timeline')
        ->assertOk()
        ->assertSet('isTeacher', true)
        ->assertSet('hasSchedules', false)
        ->assertSee('No tienes asignaturas asignadas');
});
