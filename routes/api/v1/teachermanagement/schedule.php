<?php

use App\Http\Controllers\Api\V1\TeacherManagement\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.rotated', 'token.ability:schedule.read'])
    ->prefix('teachermanagement')
    ->group(function (): void {
        Route::get('/schedules', [ScheduleController::class, 'index']);
    });
