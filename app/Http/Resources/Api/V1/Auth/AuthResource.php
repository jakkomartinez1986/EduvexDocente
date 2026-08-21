<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Auth;

use App\Services\Api\V1\Auth\LoginResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AuthResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LoginResult $result */
        $result = $this->resource;

        return [
            'access_token' => $result->token,
            'token_type' => $result->tokenType,
            'expires_at' => $result->expiresAt->toISOString(),
            'user' => new UserResource($result->user),
        ];
    }
}
