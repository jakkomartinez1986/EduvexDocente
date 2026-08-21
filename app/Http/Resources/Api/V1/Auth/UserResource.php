<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'lastname' => $user->lastname,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'dni' => $user->dni,
            'status' => (int) $user->status,
            'must_change_password' => (bool) $user->must_change_password,
            'profile_photo_url' => $user->defaultUserPhotoUrl(),
            'roles' => $user->getRoleNames()->sort()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
        ];
    }
}
