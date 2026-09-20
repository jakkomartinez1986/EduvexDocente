<?php

use App\Http\Controllers\Api\V1\Academic\GradesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.rotated', 'token.ability:grades.write'])
    ->prefix('grades')
    ->group(function (): void {
        Route::post('/blocks', [GradesController::class, 'storeBlock']);
        Route::delete('/blocks/{block}', [GradesController::class, 'destroyBlock']);
        Route::post('/activities', [GradesController::class, 'storeActivity']);
        Route::put('/activities/{activity}', [GradesController::class, 'updateActivity']);
        Route::delete('/activities/{activity}', [GradesController::class, 'destroyActivity']);
        Route::put('/activities/{activity}/grades', [GradesController::class, 'storeActivityGrades']);
        Route::post('/activities/{activity}/recoveries', [GradesController::class, 'storeRecovery']);
        Route::put('/exams', [GradesController::class, 'storeExams']);
        Route::put('/projects', [GradesController::class, 'storeProjects']);
        Route::put('/supplementary', [GradesController::class, 'storeSupplementary']);
        Route::post('/recoveries/{recovery}/apply', [GradesController::class, 'applyRecovery']);
    });
