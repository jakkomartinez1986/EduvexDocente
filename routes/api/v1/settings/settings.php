<?php

use App\Http\Controllers\Api\V1\Configuration\ConfigurationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/settings', [ConfigurationController::class, 'index']);
});
