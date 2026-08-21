<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsRepresentative
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermissionTo('ver-portal-representante')) {
            abort(403, 'No tienes permisos para acceder al Portal del Representante.');
        }

        return $next($request);
    }
}
