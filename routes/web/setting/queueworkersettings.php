<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // ── Con. Esc: Monitoreo de Trabajadores ──
    Route::prefix('system/settings/queue-workers')->name('admin.settings.queue-workers.')->group(function () {
        Route::livewire('/', 'pages::system.settings.queue.workers.index')->name('index');
    });
});
