<?php

use App\Http\Controllers\Api\V1\TeacherManagement\AttendanceController;
use App\Http\Controllers\Api\V1\TeacherManagement\AttendanceRegisterController;
use App\Http\Controllers\Api\V1\TeacherManagement\AttendanceSummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('teachermanagement')->group(function () {
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::get('/attendances/register', [AttendanceRegisterController::class, 'show']);
    Route::put('/attendances/register', [AttendanceRegisterController::class, 'update']);
    Route::get('/attendances/summary', [AttendanceSummaryController::class, 'index']);
});
