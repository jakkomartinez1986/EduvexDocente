<?php

use App\Exceptions\Api\PasswordChangeRequiredException;
use App\Exceptions\Api\TokenAbilityMissingException;
use App\Http\Middleware\EnsurePasswordRotated;
use App\Http\Middleware\EnsureTokenAbility;
use App\Http\Middleware\EnsureUserIsActive;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'user.active' => EnsureUserIsActive::class,
            'token.ability' => EnsureTokenAbility::class,
            'password.rotated' => EnsurePasswordRotated::class,
            // 'representante' => \App\Http\Middleware\EnsureUserIsRepresentative::class,
        ]);

        $middleware->group('auth', [
            Authenticate::class,
            'user.active',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    message: 'Los datos proporcionados no son válidos.',
                    errors: $e->errors(),
                    status: 422,
                );
            }
        });
        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    message: 'No autenticado. Token inválido o ausente.',
                    status: 401,
                );
            }
        });

        $exceptions->renderable(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                // Se preservan Retry-After y X-RateLimit-* para que el
                // motor de sync del cliente respete el backoff (§7.7).
                return ApiResponse::error(
                    message: 'Demasiadas solicitudes. Inténtelo nuevamente más tarde.',
                    status: 429,
                )->withHeaders($e->getHeaders());
            }
        });

        $exceptions->renderable(function (PostTooLargeException $e, Request $request) {
            if ($request->is('api/*')) {
                // Lote de sync (o request) que excede post_max_size: el
                // cliente debe trocear más pequeño el outbox (§5).
                return ApiResponse::error(
                    message: 'El cuerpo de la solicitud excede el tamaño permitido. Reduzca el lote.',
                    status: 413,
                    meta: ['code' => 'payload_too_large'],
                );
            }
        });

        $exceptions->renderable(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    message: $e->getMessage() ?: 'No autorizado.',
                    status: 403,
                );
            }
        });

        $exceptions->renderable(function (PasswordChangeRequiredException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    message: $e->getMessage(),
                    status: 403,
                    meta: ['code' => 'password_change_required'],
                );
            }
        });

        $exceptions->renderable(function (TokenAbilityMissingException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    message: $e->getMessage(),
                    status: 403,
                    meta: [
                        'code' => 'insufficient_abilities',
                        'required_abilities' => $e->required,
                    ],
                );
            }
        });

        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    message: $e->getMessage() ?: 'No se encontró el recurso solicitado.',
                    status: 404,
                );
            }
        });
    })->create();
