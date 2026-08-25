<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

use RuntimeException;

/**
 * El token es válido pero carece de la ability requerida por el endpoint
 * (H-11: mínimo privilegio por módulo). Se renderiza como envelope 403
 * con meta.code = "insufficient_abilities".
 */
final class TokenAbilityMissingException extends RuntimeException
{
    /**
     * @param  array<int, string>  $required
     */
    public function __construct(public readonly array $required = [])
    {
        parent::__construct('El token no tiene las habilidades requeridas para esta operación.');
    }
}
