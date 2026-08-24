<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Sync;

/**
 * Tipo de desenlace de la detección de conflictos §7.6.
 */
enum ConflictKind: string
{
    /** Otro autor escribió algo más nuevo y distinto: bloquea hasta force. */
    case Conflict = 'conflict';

    /** El propio docente tiene una versión más nueva en servidor: aviso no bloqueante. */
    case SameAuthorNewer = 'same_author_newer';
}
