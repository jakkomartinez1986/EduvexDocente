<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Sync;

/**
 * Resultado de la detección de conflictos §7.6 sobre una operación de sync.
 * Null del detector = sin observaciones (accepted normal).
 */
final readonly class ConflictOutcome
{
    /**
     * @param  array<string, mixed>|null  $serverRecord
     */
    private function __construct(
        public ConflictKind $kind,
        public ?array $serverRecord = null,
    ) {}

    /**
     * @param  array<string, mixed>  $serverRecord
     */
    public static function conflict(array $serverRecord): self
    {
        return new self(ConflictKind::Conflict, $serverRecord);
    }

    public static function sameAuthorNewer(): self
    {
        return new self(ConflictKind::SameAuthorNewer);
    }

    public function isConflict(): bool
    {
        return $this->kind === ConflictKind::Conflict;
    }

    public function isSameAuthorNewer(): bool
    {
        return $this->kind === ConflictKind::SameAuthorNewer;
    }
}
