<?php

use App\Http\Controllers\Web\System\Teacher\IncidentPdfController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {

    // ── Libro de Incidencias ──
    Route::prefix('system/teacher/incidents')->name('admin.teacher.incidents.')->group(function () {
        Route::livewire('/', 'pages::system.teachers-management.teachers.incidents.index')->name('index');
    });

    // ── PDF generation ──
    Route::prefix('system/teacher/incidents/pdf')->name('admin.teacher.incidents.pdf.')->group(function () {
        Route::get('/notification/{id}', [IncidentPdfController::class, 'notification'])->name('notification');
        Route::get('/commitment-letter/{id}', [IncidentPdfController::class, 'commitmentLetter'])->name('commitment-letter');
        Route::get('/report/{id}', [IncidentPdfController::class, 'report'])->name('report');
    });
});
