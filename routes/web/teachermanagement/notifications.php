<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('system/teacher/notifications')->name('admin.teacher.notifications.')->group(function () {
    Route::livewire('/', 'pages::system.teachers-management.teachers.notifications.index')->name('index');
});
