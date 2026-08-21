<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Configuration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrato de la respuesta de GET /api/v1/configuration.
 *
 * Recibe el payload ya construido por ConfigurationService y lo expone tal
 * cual; su responsabilidad es fijar la forma pública del documento.
 */
final class ConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return $payload;
    }
}
