<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Identity\SuperAdminService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra el registro publico cuando ya existe un super administrador.
 *
 * El primer alta corresponde al super administrador; una vez creada su cuenta
 * el sistema queda con un unico administrador privileged, por lo que las
 * rutas de registro de Fortify responden 404 y el enlace "Sign up" desaparece
 * del formulario de acceso. Las demas rutas de la aplicacion no se ven
 * afectadas: el guard solo consulta cuando la peticion apunta al registro.
 */
class EnsureRegistrationIsOpen
{
    /**
     * Rutas de Fortify que crean una cuenta nueva.
     */
    private const REGISTRATION_ROUTES = ['register', 'register.store'];

    public function __construct(private readonly SuperAdminService $superAdmin) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isRegistrationRequest($request) && $this->superAdmin->exists()) {
            abort(404);
        }

        return $next($request);
    }

    /**
     * Indica si la peticion apunta a una de las rutas de registro.
     */
    private function isRegistrationRequest(Request $request): bool
    {
        $route = $request->route();

        $name = is_object($route) ? $route->getName() : null;

        return in_array($name, self::REGISTRATION_ROUTES, true);
    }
}
