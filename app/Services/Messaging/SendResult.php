<?php

namespace App\Services\Messaging;

final class SendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalId = null,
        public readonly ?string $error = null,
    ) {}

    public static function ok(?string $externalId = null): self
    {
        return new self(true, $externalId);
    }

    public static function fail(string $error): self
    {
        return new self(false, null, $error);
    }
}
