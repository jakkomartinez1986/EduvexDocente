<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('system/security')->name('admin.')->group(function () {

    // Roles
    Route::livewire('roles', 'pages::system.security.roles.index')->name('roles.index');
    Route::livewire('roles/create', 'pages::system.security.roles.create')->name('roles.create');
    Route::livewire('roles/{id}', 'pages::system.security.roles.show')->name('roles.show');
    Route::livewire('roles/{id}/edit', 'pages::system.security.roles.edit')->name('roles.edit');

    // Permisos
    Route::livewire('permissions', 'pages::system.security.permissions.index')->name('permissions.index');
    Route::livewire('permissions/create', 'pages::system.security.permissions.create')->name('permissions.create');
    Route::livewire('permissions/{id}', 'pages::system.security.permissions.show')->name('permissions.show');
    Route::livewire('permissions/{id}/edit', 'pages::system.security.permissions.edit')->name('permissions.edit');
});
