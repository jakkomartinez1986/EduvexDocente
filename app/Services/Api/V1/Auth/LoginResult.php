<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Auth;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Resultado de una autenticación exitosa.
 */
final readonly class LoginResult
{
    public function __construct(
        public string $token,
        public string $tokenType,
        public CarbonInterface $expiresAt,
        public User $user,
    ) {}
}
