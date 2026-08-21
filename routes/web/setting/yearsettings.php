<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // ── Con. Año Esc: Año de Gestion ──
    Route::prefix('system/settings/years')->name('admin.settings.years.')->group(function () {
        Route::livewire('/', 'pages::system.settings.year.scolar-years.index')->name('index');
        Route::livewire('/create', 'pages::system.settings.year.scolar-years.create')->name('create');
        Route::livewire('/{id}', 'pages::system.settings.year.scolar-years.show')->name('show');
        Route::livewire('/{id}/edit', 'pages::system.settings.year.scolar-years.edit')->name('edit');
    });

    // ── Con. Año Esc: Periodos ──
    Route::prefix('system/settings/trimesters')->name('admin.settings.trimesters.')->group(function () {
        Route::livewire('/', 'pages::system.settings.year.academic-periods.index')->name('index');
        Route::livewire('/create', 'pages::system.settings.year.academic-periods.create')->name('create');
        Route::livewire('/{id}', 'pages::system.settings.year.academic-periods.show')->name('show');
        Route::livewire('/{id}/edit', 'pages::system.settings.year.academic-periods.edit')->name('edit');
    });

    // ── Con. Año Esc: Calendario Escolar ──
    Route::prefix('system/settings/calendar-scolars')->name('admin.settings.calendar-scolars.')->group(function () {
        Route::livewire('/', 'pages::system.settings.year.calendar-days.index')->name('index');
        Route::livewire('/import', 'pages::system.settings.year.calendar-days.import')->name('import');
        Route::livewire('/create', 'pages::system.settings.year.calendar-days.create')->name('create');
        Route::livewire('/{id}', 'pages::system.settings.year.calendar-days.show')->name('show');
        Route::livewire('/{id}/edit', 'pages::system.settings.year.calendar-days.edit')->name('edit');
    });

    // ── Con. Año Esc: Conf. Calificaciones ──
    Route::prefix('system/settings/grading-schemes')->name('admin.settings.grading-schemes.')->group(function () {
        Route::livewire('/', 'pages::system.settings.year.grading-schemes.index')->name('index');
        Route::livewire('/create', 'pages::system.settings.year.grading-schemes.create')->name('create');
        Route::livewire('/{id}', 'pages::system.settings.year.grading-schemes.show')->name('show');
        Route::livewire('/{id}/edit', 'pages::system.settings.year.grading-schemes.edit')->name('edit');
    });
});
