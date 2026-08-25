<?php

use App\Http\Controllers\Api\V1\Configuration\ConfigurationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.rotated', 'token.ability:configuration.read'])
    ->group(function (): void {
        Route::get('/configuration', [ConfigurationController::class, 'index']);
    });
