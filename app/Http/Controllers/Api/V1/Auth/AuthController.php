<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\Api\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\Auth\AuthResource;
use App\Http\Resources\Api\V1\Auth\UserResource;
use App\Services\Api\V1\Auth\LoginService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(private readonly LoginService $loginService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->loginService->attempt(
                $request->validated('login'),
                $request->validated('password'),
            );
        } catch (InvalidCredentialsException) {
            return ApiResponse::error(
                message: 'Las credenciales proporcionadas no son válidas.',
                status: 401,
            );
        }

        return ApiResponse::success(
            data: new AuthResource($result),
        );
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: new UserResource($request->user()),
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(
            data: [
                'message' => __('Sesión cerrada correctamente.'),
            ],
        );
    }
}
