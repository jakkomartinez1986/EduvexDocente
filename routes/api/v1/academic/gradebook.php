<?php

use App\Http\Controllers\Api\V1\Academic\GradebookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.rotated', 'token.ability:grades.read'])
    ->prefix('academic')
    ->group(function (): void {
        Route::get('/gradebook', [GradebookController::class, 'index']);
        Route::get('/gradebook/download', [GradebookController::class, 'download']);
    });
