<?php

use App\Http\Controllers\Api\V1\Setting\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/settings/bootstrap', [SettingsController::class, 'bootstrap']);
});
