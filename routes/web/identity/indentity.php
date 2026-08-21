<?php

use App\Http\Controllers\Web\System\Teacher\CarnetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('system/identity')->name('system.identity.')->group(function () {

    // Usuarios
    Route::livewire('users', 'pages::system.identity.users.index')->name('users.index');
    Route::livewire('users/create', 'pages::system.identity.users.create')->name('users.create');
    Route::livewire('users/{id}', 'pages::system.identity.users.show')->name('users.show');
    Route::livewire('users/{id}/edit', 'pages::system.identity.users.edit')->name('users.edit');

    // Estudiantes (solo tutor/docente/admin puede crear/importar)
    Route::livewire('students', 'pages::system.identity.students.index')->name('students.index');
    Route::livewire('students/create', 'pages::system.identity.students.create')
        ->middleware('role:DOCENTE|TUTOR|ADMIN|SUPER-ADMIN')
        ->name('students.create');
    Route::livewire('students/import/{gradeId?}', 'pages::system.identity.students.import')
        ->middleware('role:DOCENTE|TUTOR|ADMIN|SUPER-ADMIN')
        ->name('students.import');
    Route::livewire('students/{id}', 'pages::system.identity.students.show')->name('students.show');
    Route::livewire('students/{id}/edit', 'pages::system.identity.students.edit')->name('students.edit');

    // Carnet Estudiantil
    Route::get('students/{id}/carnet', [CarnetController::class, 'individual'])->name('students.carnet');
    Route::get('students/carnets/bulk-pdf', [CarnetController::class, 'bulkPdf'])
        ->middleware('role:DOCENTE|TUTOR')
        ->name('students.carnets.bulk-pdf');

    // Docentes (solo admin puede importar)
    Route::livewire('teachers', 'pages::system.identity.teachers.index')->name('teachers.index');
    Route::livewire('teachers/create', 'pages::system.identity.teachers.create')->name('teachers.create');
    Route::livewire('teachers/import', 'pages::system.identity.teachers.import')->name('teachers.import');
    Route::livewire('teachers/{id}', 'pages::system.identity.teachers.show')->name('teachers.show');
    Route::livewire('teachers/{id}/edit', 'pages::system.identity.teachers.edit')->name('teachers.edit');

    // Representantes (solo tutor/docente/admin puede crear/importar)
    Route::livewire('representatives', 'pages::system.identity.representatives.index')->name('representatives.index');
    Route::livewire('representatives/create', 'pages::system.identity.representatives.create')
        ->middleware('role:DOCENTE|TUTOR|ADMIN|SUPER-ADMIN')
        ->name('representatives.create');
    Route::livewire('representatives/import', 'pages::system.identity.representatives.import')
        ->middleware('role:DOCENTE|TUTOR|ADMIN|SUPER-ADMIN')
        ->name('representatives.import');
    Route::livewire('representatives/{id}', 'pages::system.identity.representatives.show')->name('representatives.show');
    Route::livewire('representatives/{id}/edit', 'pages::system.identity.representatives.edit')->name('representatives.edit');

    // Descarga de plantillas Excel
    Route::get('templates/{type}/download', function (string $type) {
        $templates = [
            'estudiantes' => ['file' => 'plantilla_estudiantes.xlsx', 'name' => 'plantilla_estudiantes'],
            'docentes' => ['file' => 'plantilla_docentes.xlsx', 'name' => 'plantilla_docentes'],
            'representantes' => ['file' => 'plantilla_representantes.xlsx', 'name' => 'plantilla_representantes'],
        ];

        if (! isset($templates[$type])) {
            abort(404);
        }

        $template = $templates[$type];
        $path = storage_path('app/templates/'.$template['file']);

        if (! file_exists($path)) {
            abort(404);
        }

        return response()->download($path, $template['name'].'_'.date('Y-m-d').'.xlsx', [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    })->name('templates.download');
});
