<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // ── Con. Esc: Canales de Mensajería ──
    Route::prefix('system/settings/messaging-channels')->name('admin.settings.messaging-channels.')->group(function () {
        Route::livewire('/', 'pages::system.settings.messaging.channels.index')->name('index');
    });
});
