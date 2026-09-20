<?php

namespace App\Services\Reports;

use App\Jobs\GeneratePdfReport;
use App\Services\ReportStorageService;

/**
 * Puerta de acceso al pipeline de reportes async (C-04): calcula la ruta del
 * reporte, decide si ya está listo (URL firmada) o encola su generación, y
 * expone la prueba de disponibilidad para la página de "procesando".
 */
class AsyncReportDispatcher
{
    /**
     * Edad máxima aceptada para un reporte perseguido: si el archivo existe
     * pero es más viejo que esto (el nombre versionado no cambió pese a un
     * cambio de datos), ready() fuerza la regeneración y ya no se sirve un
     * PDF desactualizado.
     */
    public const MAX_AGE_SECONDS = 30 * 60;

    public function __construct(
        private readonly PdfReportRenderer $renderer,
        private readonly ReportStorageService $storage,
    ) {}

    public function path(string $type, string $filename): string
    {
        return 'reports/'.$this->renderer->subdirectory($type).'/'.$filename;
    }

    public function ready(string $path): bool
    {
        if (! $this->storage->exists($path)) {
            return false;
        }

        $modifiedAt = $this->storage->lastModified($path);

        // Sin metadatos (disco no compatible) no se puede descartar el
        // archivo: se sirve y la capa 1 (invalidación push) sigue mandando.
        if ($modifiedAt === null) {
            return true;
        }

        return now()->getTimestamp() - $modifiedAt <= self::MAX_AGE_SECONDS;
    }

    public function signedUrl(string $path): string
    {
        return $this->storage->url($path);
    }

    public function dispatch(string $type, ?int $entityId, array $context = []): void
    {
        GeneratePdfReport::dispatch($type, $entityId, $context);
    }
}
