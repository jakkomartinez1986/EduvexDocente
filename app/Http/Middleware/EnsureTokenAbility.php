<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Api\TokenAbilityMissingException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * H-11: exige que el token Sanctum tenga al menos una de las abilities
 * indicadas (semántica any-of, igual que el middleware nativo de Sanctum,
 * pero con envelope uniforme vía TokenAbilityMissingException).
 *
 * Uso: Route::middleware('token.ability:grades.write')
 */
final class EnsureTokenAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        $authorized = $user !== null && collect($abilities)->contains(
            fn (string $ability): bool => $user->tokenCan($ability),
        );

        if (! $authorized) {
            throw new TokenAbilityMissingException($abilities);
        }

        return $next($request);
    }
}
