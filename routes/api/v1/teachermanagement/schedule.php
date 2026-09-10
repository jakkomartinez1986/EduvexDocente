<?php

use App\Http\Controllers\Api\V1\TeacherManagement\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.rotated'])
    ->prefix('teachermanagement')
    ->group(function (): void {
        Route::middleware('token.ability:schedule.read')
            ->group(function (): void {
                Route::get('/schedules', [ScheduleController::class, 'index']);
            });

        Route::middleware('token.ability:schedule.write')
            ->group(function (): void {
                Route::post('/schedules', [ScheduleController::class, 'store']);
                Route::put('/schedules/{schedule}', [ScheduleController::class, 'update']);
                Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy']);
            });
    });
