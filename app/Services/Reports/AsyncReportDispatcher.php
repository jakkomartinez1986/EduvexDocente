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
        return $this->storage->exists($path);
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
