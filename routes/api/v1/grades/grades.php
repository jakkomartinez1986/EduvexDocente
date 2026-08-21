<?php

use App\Http\Controllers\Api\V1\Academic\GradesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('grades')->group(function () {
    Route::get('/', [GradesController::class, 'index']);
    Route::post('/blocks', [GradesController::class, 'storeBlock']);
    Route::post('/activities', [GradesController::class, 'storeActivity']);
    Route::put('/activities/{activity}', [GradesController::class, 'updateActivity']);
    Route::put('/activities/{activity}/grades', [GradesController::class, 'storeActivityGrades']);
    Route::post('/activities/{activity}/recoveries', [GradesController::class, 'storeRecovery']);
    Route::put('/exams', [GradesController::class, 'storeExams']);
    Route::put('/projects', [GradesController::class, 'storeProjects']);
    Route::put('/supplementary', [GradesController::class, 'storeSupplementary']);
    Route::post('/recoveries/{recovery}/apply', [GradesController::class, 'applyRecovery']);
});
