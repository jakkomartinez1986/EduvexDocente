<?php

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\Academic\PdfReportCache;
use App\Services\Reports\GradebookPdfService;

beforeEach(function (): void {
    Cache::flush();
});

it('versiona el nombre del reporte formativo al invalidar el bucket de la asignatura', function (): void {
    $context = academicContext();

    $ctx = [
        'teacher_id' => $context['teacher']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
    ];

    $service = app(GradebookPdfService::class);

    $before = $service->filename(GradebookPdfService::FORMATIVE, $ctx);

    app(PdfReportCache::class)->invalidateForSubjectGrade((int) $context['subject']->id, (int) $context['grade']->id);

    $after = $service->filename(GradebookPdfService::FORMATIVE, $ctx);

    expect($before)->toEndWith('.pdf')
        ->and($after)->toEndWith('.pdf')
        ->and($before)->not->toBe($after);
});

it('versiona el reporte individual del estudiante al invalidar su bucket', function (): void {
    $context = academicContext();
    createTutorSchedule($context);

    $student = Student::factory()->create();
    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]);

    $ctx = [
        'teacher_id' => $context['teacher']->id,
        'student_id' => $student->id,
        'trimester_id' => $context['trimester']->id,
    ];

    $service = app(GradebookPdfService::class);

    $before = $service->filename(GradebookPdfService::TUTOR_STUDENT_TRI, $ctx);

    app(PdfReportCache::class)->invalidateForStudent((int) $student->id);

    $after = $service->filename(GradebookPdfService::TUTOR_STUDENT_TRI, $ctx);

    expect($before)->not->toBe($after);
});

it('versiona el reporte de todos los estudiantes al invalidar una asignatura del grado', function (): void {
    $context = academicContext();
    createTutorSchedule($context);

    $ctx = [
        'teacher_id' => $context['teacher']->id,
        'trimester_id' => $context['trimester']->id,
    ];

    $service = app(GradebookPdfService::class);

    $before = $service->filename(GradebookPdfService::TUTOR_ALL_TRI, $ctx);

    app(PdfReportCache::class)->invalidateForSubjectGrade((int) $context['subject']->id, (int) $context['grade']->id);

    $after = $service->filename(GradebookPdfService::TUTOR_ALL_TRI, $ctx);

    expect($before)->not->toBe($after);
});

/**
 * @param  array<string, mixed>  $context
 */
function createTutorSchedule(array $context): void
{
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
}
