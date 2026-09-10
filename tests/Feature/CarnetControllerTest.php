<?php

use App\Jobs\GeneratePdfReport;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use App\Services\Reports\PdfReportRenderer;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Role;

function carnetDocenteRole(): Role
{
    return Role::firstOrCreate(
        ['name' => 'DOCENTE', 'guard_name' => 'web'],
        ['description' => 'Docente'],
    );
}

beforeEach(function (): void {
    Cache::flush();
});

function authenticatedTeacher(array $context): User
{
    $user = $context['teacher']->user;
    $user->assignRole(carnetDocenteRole());
    if (! $user->email_verified_at) {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    return $user;
}

it('encola el carnet masivo del grado y muestra la página de espera', function (): void {
    Bus::fake();

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

    $this->actingAs(authenticatedTeacher($context))
        ->get(route('system.identity.students.carnets.bulk-pdf'))
        ->assertOk()
        ->assertViewIs('reports.processing')
        ->assertSee('Generando Carnets del grado');

    Bus::assertDispatched(GeneratePdfReport::class, fn (GeneratePdfReport $job) => $job->type === PdfReportRenderer::CARNET_BULK
        && $job->entityId === null
        && $job->context['teacher_id'] === $context['teacher']->id);
});

it('redirige atrás con error cuando el docente no tiene asignación de tutoría', function (): void {
    Bus::fake();

    $context = academicContext();

    $this->actingAs(authenticatedTeacher($context))
        ->from('/system/identity/students')
        ->get(route('system.identity.students.carnets.bulk-pdf'))
        ->assertRedirect('/system/identity/students')
        ->assertSessionHas('error', 'No se encontró asignación de tutoría.');

    Bus::assertNotDispatched(GeneratePdfReport::class);
});
