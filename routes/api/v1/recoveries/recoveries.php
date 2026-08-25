<?php

use App\Http\Controllers\Api\V1\Academic\RecoveriesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.rotated'])
    ->prefix('recoveries')
    ->group(function (): void {
        Route::middleware('token.ability:grades.read')->group(function (): void {
            Route::get('/recoverable', [RecoveriesController::class, 'recoverable']);
            Route::get('/applied', [RecoveriesController::class, 'applied']);
        });

        Route::middleware('token.ability:grades.write')->group(function (): void {
            Route::post('/exams', [RecoveriesController::class, 'storeExam']);
            Route::post('/exams/{examRecovery}/apply', [RecoveriesController::class, 'applyExam']);
            Route::delete('/exams/{examRecovery}', [RecoveriesController::class, 'destroyExam']);
            Route::delete('/activities/{activityRecovery}', [RecoveriesController::class, 'destroyActivity']);
        });
    });
