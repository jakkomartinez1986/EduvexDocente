<?php

use App\Http\Controllers\Web\System\Teacher\GradebookPdfController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {

    // ── Horario (ClassSchedule) ──
    Route::prefix('system/teacher/schedules')->name('admin.teacher.schedule.')->group(function () {
        Route::livewire('/', 'pages::system.teacher.schedules.index')->name('index');
        Route::livewire('/timeline', 'pages::system.teachers-management.teachers.schedules.timeline')->name('timeline');
        Route::livewire('/create', 'pages::system.teachers-management.teachers.schedules.create')->name('create');
        Route::livewire('/{id}', 'system.teacher.schedules.show')->name('show');
        Route::livewire('/{id}/edit', 'pages::system.teachers-management.teachers.schedules.edit')->name('edit');
        Route::livewire('/{id}/incidents', 'system.teacher.schedules.incidents')->name('incidents');
    });

    // ── Observaciones de Clase (ClassObservation) ──
    Route::prefix('system/teacher/observations')->name('admin.teacher.observations.')->group(function () {
        Route::livewire('/', 'system.teacher.observations.index')->name('index');
        Route::livewire('/create', 'system.teacher.observations.create')->name('create');
        Route::livewire('/{id}', 'system.teacher.observations.show')->name('show');
        Route::livewire('/{id}/edit', 'system.teacher.observations.edit')->name('edit');
    });

    // ── Asistencias (Attendance) ──
    Route::prefix('system/teacher/attendances')->name('admin.teacher.attendances.')->group(function () {
        Route::livewire('/', 'system.teacher.attendances.index')->name('index');
        Route::livewire('/create', 'system.teacher.attendances.create')->name('create');
        Route::livewire('/{id}', 'system.teacher.attendances.show')->name('show');
        Route::livewire('/{id}/edit', 'system.teacher.attendances.edit')->name('edit');
    });

    // ── Bloques de Evaluacion (AssessmentBlock) ──
    Route::prefix('system/summaries/assessment-blocks')->name('admin.summaries.assessment-blocks.')->group(function () {
        Route::livewire('/', 'system.teacher.assessment-blocks.index')->name('index');
        Route::livewire('/create', 'system.teacher.assessment-blocks.create')->name('create');
        Route::livewire('/{id}', 'system.teacher.assessment-blocks.show')->name('show');
        Route::livewire('/{id}/edit', 'system.teacher.assessment-blocks.edit')->name('edit');
    });

    // ── Libro de Calificaciones (GradeBook) ──
    Route::prefix('system/summaries/gradebook')->name('admin.summaries.gradebook.')->group(function () {
        Route::livewire('/', 'pages::system.teachers-management.teachers.gradebook.index')->name('index');

        Route::prefix('pdf')->name('pdf.')->group(function () {
            Route::get('/print-formative', [GradebookPdfController::class, 'formative'])->name('print-formative');
            Route::get('/print-summative', [GradebookPdfController::class, 'summative'])->name('print-summative');
            Route::get('/subject-annual-report', [GradebookPdfController::class, 'subjectAnnualReport'])->name('subject-annual-report');
            Route::get('/supletorio-report', [GradebookPdfController::class, 'supletorioReport'])->name('supletorio-report');
            Route::get('/qualitative-report', [GradebookPdfController::class, 'qualitativeReport'])->name('qualitative-report');
        });
    });

    // ── Horario del Grado (Tutor) ──
    Route::prefix('system/teacher/tutor-schedule')->name('admin.teacher.tutor-schedule.')->group(function () {
        Route::livewire('/', 'pages::system.teachers-management.tutors.schedule.index')->name('index');
    });

    // ── Mis Estudiantes (Tutor) ──
    Route::prefix('system/teacher/tutor-students')->name('admin.teacher.tutor-students.')->group(function () {
        Route::livewire('/', 'pages::system.teachers-management.tutors.students.index')->name('index');
    });

    // ── Representantes del Grado (Tutor) ──
    Route::prefix('system/teacher/tutor-representatives')->name('admin.teacher.tutor-representatives.')->group(function () {
        Route::livewire('/', 'pages::system.teachers-management.tutors.representatives.index')->name('index');
    });

    // ── Justificaciones (Tutor) ──
    Route::prefix('system/teacher/tutor-justifications')->name('admin.teacher.tutor-justifications.')->group(function () {
        Route::livewire('/', 'pages::system.teachers-management.tutors.justifications.index')->name('index');
        Route::livewire('/{id}', 'pages::system.teachers-management.tutors.justifications.show')->name('show');
    });

    // ── Libro de Asistencias del Grado (Tutor) ──
    Route::prefix('system/teacher/tutor-attendance-book')->name('admin.teacher.tutor-attendance-book.')->group(function () {
        Route::livewire('/', 'pages::system.teachers-management.tutors.attendance-book.index')->name('index');
    });

    // ── Reportes de Notas del Tutor ──
    Route::prefix('system/teacher/tutor-grade-reports')->name('admin.teacher.tutor-grade-reports.')->group(function () {
        Route::livewire('/', 'pages::system.teachers-management.tutors.reports.index')->name('index');
        Route::get('/pdf/student-report', [GradebookPdfController::class, 'tutorStudentReport'])->name('pdf.student-report');
        Route::get('/pdf/student-report-trimester', [GradebookPdfController::class, 'tutorStudentReportByTrimester'])->name('pdf.student-report-trimester');
        Route::get('/pdf/student-formative-trimester', [GradebookPdfController::class, 'tutorStudentFormativeByTrimester'])->name('pdf.student-formative-trimester');
        Route::get('/pdf/all-students-trimester', [GradebookPdfController::class, 'tutorAllStudentsTrimesterReport'])->name('pdf.all-students-trimester');
        Route::get('/pdf/student-annual-report', [GradebookPdfController::class, 'studentAnnualReport'])->name('pdf.student-annual-report');
        Route::get('/pdf/student-trimester-report', [GradebookPdfController::class, 'studentTrimesterReport'])->name('pdf.student-trimester-report');
    });

    // ── Libro de Asistencias (Attendance Book) ──
    Route::prefix('system/teacher/attendance-book')->name('admin.teacher.attendance-book.')->group(function () {
        Route::livewire('/', 'pages::system.teachers-management.teachers.attendances.attendance-book.index')->name('index');
        Route::livewire('/{id}', 'pages::system.teachers-management.teachers.attendances.attendance-book.show')->name('show');
    });

    // ── Registro de Asistencia (Attendance Register) ──
    Route::prefix('system/teacher/attendance-register')->name('admin.teacher.attendance-register.')->group(function () {
        Route::livewire('/', 'pages::system.teachers-management.teachers.attendances.attendance-register.index')->name('index');
    });

    // ── Recuperaciones (ActivityRecovery) ──
    Route::prefix('system/teacher/recoveries')->name('admin.teacher.recoveries.')->group(function () {
        Route::livewire('/', 'pages::system.teachers-management.teachers.recoveries.index')->name('index');
    });

    // ── Actividades (Activity) ──
    Route::prefix('system/summaries/activities')->name('admin.summaries.activities.')->group(function () {
        Route::livewire('/', 'system.teacher.activities.index')->name('index');
        Route::livewire('/create', 'system.teacher.activities.create')->name('create');
        Route::livewire('/{id}', 'system.teacher.activities.show')->name('show');
        Route::livewire('/{id}/edit', 'system.teacher.activities.edit')->name('edit');
    });
});
