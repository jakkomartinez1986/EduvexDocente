<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api:v1')->group(function () {
    foreach (glob(__DIR__.'/api/v1/*/*.php') ?: [] as $routeFile) {
        require $routeFile;
    }
});
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API funcionando correctamente',
        'timestamp' => now()->toDateTimeString(),
    ]);
});
