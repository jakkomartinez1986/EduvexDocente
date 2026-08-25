<?php

use App\Http\Controllers\Api\V1\TeacherManagement\AttendanceController;
use App\Http\Controllers\Api\V1\TeacherManagement\AttendanceRegisterController;
use App\Http\Controllers\Api\V1\TeacherManagement\AttendanceSummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.rotated'])->prefix('teachermanagement')->group(function (): void {
    Route::middleware('token.ability:attendance.read')->group(function (): void {
        Route::get('/attendances', [AttendanceController::class, 'index']);
        Route::get('/attendances/register', [AttendanceRegisterController::class, 'show']);
        Route::get('/attendances/summary', [AttendanceSummaryController::class, 'index']);
    });

    Route::put('/attendances/register', [AttendanceRegisterController::class, 'update'])
        ->middleware('token.ability:attendance.write');
});
