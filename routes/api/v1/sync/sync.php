<?php

use App\Http\Controllers\Api\V1\Sync\SyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.rotated'])
    ->prefix('sync')
    ->group(function (): void {
        Route::get('/pull', [SyncController::class, 'pull'])
            ->middleware('token.ability:sync.pull');
        Route::post('/push', [SyncController::class, 'push'])
            ->middleware(['throttle:api:sync-push', 'token.ability:sync.push']);
    });
