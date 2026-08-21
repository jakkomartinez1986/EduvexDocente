<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            if (! $user->isActive() || ($user->hasRole('ESTUDIANTE') && ! $user->hasRole('REPRESENTANTE') && ! $user->hasRole('DOCENTE') && ! $user->hasRole('TUTOR'))) {
                auth()->guard('web')->logout();

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permisos para acceder al sistema.',
                    ], 403);
                }

                return redirect()->route('login')
                    ->with('error', 'No tienes permisos para acceder al sistema. Por favor, comunícate con el administrador.');
            }

            if ($user->must_change_password && ! $request->routeIs('password.change')) {
                return redirect()->route('password.change');
            }
        }

        return $next($request);
    }
}
