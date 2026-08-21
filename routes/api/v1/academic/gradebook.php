<?php

use App\Http\Controllers\Api\V1\Academic\GradebookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('academic')->group(function () {
    Route::get('/gradebook', [GradebookController::class, 'index']);
});
