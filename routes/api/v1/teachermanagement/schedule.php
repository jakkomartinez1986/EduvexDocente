<?php

use App\Http\Controllers\Api\V1\TeacherManagement\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('teachermanagement')->group(function () {
    Route::get('/schedules', [ScheduleController::class, 'index']);
});
