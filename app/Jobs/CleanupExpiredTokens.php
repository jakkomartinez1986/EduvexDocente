<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Higiene de tokens (queue-strategy.md §3): elimina los tokens de Sanctum
 * (personal_access_tokens) ya expirados para evitar acumulación en el tiempo.
 * Idempotente y sin argumentos, pensado para ejecutarse diario desde el
 * scheduler.
 */
class CleanupExpiredTokens implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        PersonalAccessToken::query()
            ->where('expires_at', '<', now())
            ->delete();
    }
}
