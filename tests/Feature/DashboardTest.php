<?php

use App\Models\Setting\EducationalSettings\Subject;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('el dashboard carga con observaciones recientes pero sin notificaciones ni incidencias', function (): void {
    Cache::flush();

    $context = academicContext();

    $tutorSubject = Subject::factory()->create(['subject_name' => 'Acompañamiento integral en el aula']);
    ClassSchedule::factory()->create([
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'subject_id' => $tutorSubject->id,
        'grade_id' => $context['grade']->id,
        'day' => 'MARTES',
        'start_time' => '07:00',
        'end_time' => '08:00',
    ]);

    ClassObservation::factory()->create([
        'class_schedule_id' => $context['schedule']->id,
        'teacher_id' => $context['teacher']->id,
        'tutor_id' => $context['teacher']->id,
        'year_id' => $context['year']->id,
        'observation_date' => now()->toDateString(),
    ]);

    $this->actingAs($context['teacher']->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Actividad reciente');
});
