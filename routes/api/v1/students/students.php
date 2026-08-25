<?php

use App\Http\Controllers\Api\V1\Students\StudentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.rotated', 'token.ability:students.read'])
    ->group(function (): void {
        Route::get('/students', [StudentController::class, 'index']);
    });
