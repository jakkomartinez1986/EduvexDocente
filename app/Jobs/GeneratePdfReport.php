<?php

namespace App\Jobs;

use App\Services\Incidents\IncidentPdfService;
use App\Services\ReportStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Plataforma de reportes async (C-04, queue-strategy.md §3): genera el PDF de
 * un reporte, lo persiste en el Object Storage configurado y encola la
 * notificación con la URL firmada. Idempotente por tipo+entidad
 * (ShouldBeUniqueUntilProcessing) para no regenerar el mismo reporte dos veces.
 */
class GeneratePdfReport implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 180];

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    /**
     * @param  string  $type  tipo de reporte (p. ej. incident_notification).
     * @param  int  $entityId  id de la entidad fuente del reporte.
     * @param  string|null  $filename  nombre de archivo; se deriva si es null.
     */
    public function __construct(
        public readonly string $type,
        public readonly int $entityId,
        public readonly ?string $filename = null,
    ) {
        $this->onQueue('reports');
    }

    public function uniqueId(): string
    {
        return $this->type.':'.$this->entityId;
    }

    public function handle(
        IncidentPdfService $incidents,
        ReportStorageService $storage,
    ): void {
        $pdf = $this->buildPdf($incidents);

        $filename = $this->filename ?? $this->defaultFilename();

        $path = $storage->store($pdf->output(), 'incidents', $filename);

        SendReportNotification::dispatch($path, $filename);
    }

    private function buildPdf(IncidentPdfService $incidents)
    {
        return match ($this->type) {
            'incident_notification' => $incidents->notification($this->entityId),
            default => throw new \RuntimeException("Tipo de reporte no soportado: {$this->type}"),
        };
    }

    private function defaultFilename(): string
    {
        return Str::slug($this->type).'-'.$this->entityId.'.pdf';
    }
}
