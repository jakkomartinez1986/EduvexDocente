<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {

    // ── Con. Esc: Colegio ──
    Route::prefix('system/settings/schools')->name('admin.schools.')->group(function () {
        Route::livewire('/', 'pages::system.settings.educational.schools.index')->name('index');
        Route::livewire('/create', 'pages::system.settings.educational.schools.create')->name('create');
        Route::livewire('/{id}', 'pages::system.settings.educational.schools.show')->name('show');
        Route::livewire('/{id}/edit', 'pages::system.settings.educational.schools.edit')->name('edit');
    });

    // ── Con. Esc: Jornadas ──
    Route::prefix('system/settings/shifts')->name('admin.settings.shifts.')->group(function () {
        Route::livewire('/', 'pages::system.settings.educational.shifts.index')->name('index');
        Route::livewire('/create', 'pages::system.settings.educational.shifts.create')->name('create');
        Route::livewire('/{id}', 'pages::system.settings.educational.shifts.show')->name('show');
        Route::livewire('/{id}/edit', 'pages::system.settings.educational.shifts.edit')->name('edit');
    });

    // ── Con. Esc: Niveles ──
    Route::prefix('system/settings/niveles')->name('admin.settings.niveles.')->group(function () {
        Route::livewire('/', 'pages::system.settings.educational.niveles.index')->name('index');
        Route::livewire('/create', 'pages::system.settings.educational.niveles.create')->name('create');
        Route::livewire('/{id}', 'pages::system.settings.educational.niveles.show')->name('show');
        Route::livewire('/{id}/edit', 'pages::system.settings.educational.niveles.edit')->name('edit');
    });

    // ── Con. Esc: Grados ──
    Route::prefix('system/settings/grades')->name('admin.settings.grades.')->group(function () {
        Route::livewire('/', 'pages::system.settings.educational.grades.index')->name('index');
        Route::livewire('/create', 'pages::system.settings.educational.grades.create')->name('create');
        Route::livewire('/{id}', 'pages::system.settings.educational.grades.show')->name('show');
        Route::livewire('/{id}/edit', 'pages::system.settings.educational.grades.edit')->name('edit');
    });

    // ── Con. Esc: Areas ──
    Route::prefix('system/settings/areas')->name('admin.settings.areas.')->group(function () {
        Route::livewire('/', 'pages::system.settings.educational.areas.index')->name('index');
        Route::livewire('/create', 'pages::system.settings.educational.areas.create')->name('create');
        Route::livewire('/{id}', 'pages::system.settings.educational.areas.show')->name('show');
        Route::livewire('/{id}/edit', 'pages::system.settings.educational.areas.edit')->name('edit');
    });

    // ── Con. Esc: Asignaturas ──
    Route::prefix('system/settings/subjects')->name('admin.settings.subjects.')->group(function () {
        Route::livewire('/', 'pages::system.settings.educational.subjects.index')->name('index');
        Route::livewire('/create', 'pages::system.settings.educational.subjects.create')->name('create');
        Route::livewire('/{id}', 'pages::system.settings.educational.subjects.show')->name('show');
        Route::livewire('/{id}/edit', 'pages::system.settings.educational.subjects.edit')->name('edit');
    });

});
