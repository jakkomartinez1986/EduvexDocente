<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Auth;

use App\Models\User;
use App\Support\Api\ApiModules;

/**
 * Calcula las abilities de un token Sanctum a partir de los permisos
 * Spatie del usuario (H-11): nada de ['*'], mínimo privilegio por módulo.
 */
final class TokenAbilityService
{
    /**
     * @return array<int, string>
     */
    public function for(User $user): array
    {
        $permissions = $user->getAllPermissions();

        $abilities = ApiModules::BASE_ABILITIES;
        $hasAnyModule = false;

        foreach (ApiModules::ABILITIES_PER_MODULE as $module => $moduleAbilities) {
            $models = ApiModules::PERMISSION_MODELS[$module];

            $hasModule = $permissions->contains(
                fn ($permission): bool => in_array($permission->module ?? null, $models, true),
            );

            if ($hasModule) {
                $abilities = [...$abilities, ...$moduleAbilities];
                $hasAnyModule = true;
            }
        }

        if ($hasAnyModule) {
            $abilities = [...$abilities, ...ApiModules::CROSS_MODULE_ABILITIES];
        }

        return $abilities;
    }
}
