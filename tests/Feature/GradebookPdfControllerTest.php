<?php

use App\Jobs\GeneratePdfReport;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\Reports\GradebookPdfService;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    Cache::flush();
});

it('encola el reporte formativo del gradebook y muestra la página de espera', function (): void {
    Bus::fake();

    $context = academicContext();
    $user = $context['teacher']->user;
    if (! $user->email_verified_at) {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    $this->actingAs($user)
        ->get(route('admin.summaries.gradebook.pdf.print-formative', [
            'subject_id' => $context['subject']->id,
            'grade_id' => $context['grade']->id,
            'trimester_id' => $context['trimester']->id,
        ]))
        ->assertOk()
        ->assertViewIs('reports.processing')
        ->assertSee('Generando Reporte de notas formativas');

    Bus::assertDispatched(GeneratePdfReport::class, fn (GeneratePdfReport $job) => $job->type === GradebookPdfService::FORMATIVE
        && $job->entityId === null
        && $job->context['teacher_id'] === $context['teacher']->id
        && $job->context['subject_id'] === $context['subject']->id
        && $job->context['grade_id'] === $context['grade']->id
        && $job->context['trimester_id'] === $context['trimester']->id
        && $job->context['teacher_name'] === $user->fullname);
});

it('rechaza con 404 el reporte cuando el docente no tiene el horario de la asignatura', function (): void {
    Bus::fake();

    $context = academicContext();

    $other = Teacher::factory()->create();
    $user = $other->user;
    if (! $user->email_verified_at) {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    $this->actingAs($user)
        ->get(route('admin.summaries.gradebook.pdf.print-formative', [
            'subject_id' => $context['subject']->id,
            'grade_id' => $context['grade']->id,
            'trimester_id' => $context['trimester']->id,
        ]))
        ->assertNotFound();

    Bus::assertNotDispatched(GeneratePdfReport::class);
});

it('rechaza con 404 los reportes del tutor cuando no hay asignación de tutoría', function (): void {
    $context = academicContext();

    $user = $context['teacher']->user;
    if (! $user->email_verified_at) {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    $this->actingAs($user)
        ->get(route('admin.teacher.tutor-grade-reports.pdf.student-report', ['student_id' => 1]))
        ->assertNotFound();
});

it('encola el reporte del tutor por trimestre cuando existe la asignación', function (): void {
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

    $student = Student::factory()->create();
    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]);

    $user = $context['teacher']->user;
    if (! $user->email_verified_at) {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    $this->actingAs($user)
        ->get(route('admin.teacher.tutor-grade-reports.pdf.student-report-trimester', [
            'student_id' => $student->id,
            'trimester_id' => $context['trimester']->id,
        ]))
        ->assertOk()
        ->assertViewIs('reports.processing');

    Bus::assertDispatched(GeneratePdfReport::class, fn (GeneratePdfReport $job) => $job->type === GradebookPdfService::TUTOR_STUDENT_TRI
        && $job->context['student_id'] === $student->id
        && $job->context['trimester_id'] === $context['trimester']->id);
});
