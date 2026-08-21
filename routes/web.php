<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');
});

require __DIR__.'/settings.php';
// Identity & Security routes are loaded from routes/web/ subdirectories
$webDir = __DIR__.'/web';
foreach (glob($webDir.'/*/*.php') as $routeFile) {
    require $routeFile;
}
