<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Auth;

use App\Exceptions\Api\InvalidCredentialsException;
use App\Exceptions\Api\PasswordChangeRequiredException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Lógica de autenticación de la API v1.
 *
 * Acepta correo electrónico o DNI en el campo `login`. La búsqueda no
 * modifica la información almacenada; únicamente normaliza la entrada.
 */
final class LoginService
{
    public function __construct(private readonly TokenAbilityService $tokenAbilityService) {}

    public function attempt(string $login, string $password): LoginResult
    {
        $user = $this->resolveUser($login);

        if ($user === null || ! Hash::check($password, $user->password) || ! $user->isActive()) {
            throw new InvalidCredentialsException;
        }

        if ((bool) $user->must_change_password) {
            throw new PasswordChangeRequiredException;
        }

        $ttl = now()->addMinutes((int) config('api.token.ttl_minutes', 1440));

        $token = $user->createToken(
            (string) config('api.token.name', 'api-access-token'),
            $this->tokenAbilityService->for($user),
            $ttl,
        );

        return new LoginResult(
            token: $token->plainTextToken,
            tokenType: 'Bearer',
            expiresAt: $ttl,
            user: $user,
        );
    }

    private function resolveUser(string $login): ?User
    {
        $login = trim($login);

        if ($login === '') {
            return null;
        }

        if (Str::contains($login, '@')) {
            $email = strtolower($login);

            return User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
        }

        $dni = preg_replace('/\s+/', '', $login);

        return User::query()
            ->where('dni', $dni)
            ->first();
    }
}
