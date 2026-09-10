<?php

use App\Http\Controllers\Api\V1\Teacher\DashboardController;
use App\Http\Controllers\Api\V1\Teacher\ParentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.rotated'])->group(function (): void {
    Route::get('/teacher/dashboard', [DashboardController::class, 'index'])
        ->middleware('token.ability:auth.me');

    Route::get('/teacher/parents', [ParentController::class, 'index'])
        ->middleware('token.ability:students.read');
});
