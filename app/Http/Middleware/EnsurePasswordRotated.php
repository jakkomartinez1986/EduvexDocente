<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Api\PasswordChangeRequiredException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * D2: bloquea los endpoints de datos cuando la cuenta tiene pendiente el
 * cambio obligatorio de contraseña (p. ej. la marca se activó después de
 * emitirse el token). /auth/me y /auth/logout permanecen accesibles para
 * que el cliente pueda recuperarse; el resto responde 403
 * password_change_required. Tras rotar la contraseña en la web, el mismo
 * token vuelve a operar.
 */
final class EnsurePasswordRotated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && (bool) $user->must_change_password) {
            throw new PasswordChangeRequiredException;
        }

        return $next($request);
    }
}
