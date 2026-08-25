<?php

use App\Http\Controllers\Api\V1\Setting\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.rotated', 'token.ability:configuration.read'])
    ->group(function (): void {
        Route::get('/settings/bootstrap', [SettingsController::class, 'bootstrap']);
    });
