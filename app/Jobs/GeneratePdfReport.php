<?php

namespace App\Jobs;

use App\Services\Reports\PdfReportRenderer;
use App\Services\ReportStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Plataforma de reportes async (C-04, queue-strategy.md §3): genera el PDF de
 * un reporte (incidencias, carnets, gradebook) vía PdfReportRenderer, lo
 * persiste en el Object Storage configurado y encola la notificación con la URL
 * firmada. Idempotente por tipo+entidad (o por tipo+contexto hash cuando no hay
 * entidad) para no regenerar el mismo reporte dos veces.
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
     * @param  string  $type  tipo de reporte (constantes de PdfReportRenderer).
     * @param  int|null  $entityId  id de la entidad fuente; null para reportes
     *                              que solo dependen del contexto (p. ej. carnet_bulk).
     * @param  array<string, mixed>  $context  contexto serializable (ids,
     *                                         teacher_id, teacher_name) que el
     *                                         renderer usa para reconstruir los datos.
     * @param  string|null  $channel  canal destinatario de la notificación.
     * @param  string|null  $to  destinatario del canal.
     */
    public function __construct(
        public readonly string $type,
        public readonly ?int $entityId = null,
        public readonly array $context = [],
        public readonly ?string $channel = null,
        public readonly ?string $to = null,
    ) {
        $this->onQueue('reports');
    }

    public function uniqueId(): string
    {
        $key = $this->entityId ?? md5($this->context !== [] ? serialize($this->context) : uniqid('', true));

        return $this->type.':'.$key;
    }

    public function handle(
        PdfReportRenderer $renderer,
        ReportStorageService $storage,
    ): void {
        $result = $renderer->render($this->type, $this->entityId, $this->context);

        $path = $storage->store(
            $result['binary'],
            $renderer->subdirectory($this->type),
            $result['filename'],
        );

        SendReportNotification::dispatch($path, $result['filename'], $this->channel, $this->to);
    }
}
