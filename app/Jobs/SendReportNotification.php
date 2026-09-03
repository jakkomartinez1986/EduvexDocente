<?php

namespace App\Jobs;

use App\Services\ReportStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Plataforma de reportes async (C-04): tras persistir un reporte en Object
 * Storage, resuelve la URL firmada y la entrega por el canal indicado
 * (reutiliza SendChannelMessageJob). Con canal null solo queda el reporte
 * accesible por URL; es el punto donde futuros productores (email/sistema)
 * avisan al docente con el enlace de descarga.
 */
class SendReportNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 180];

    public int $timeout = 120;

    /**
     * @param  string  $path  clave del reporte devuelta por ReportStorageService::store().
     * @param  string  $filename  nombre del archivo para presentarlo.
     * @param  string|null  $channel  canal destinatario (null = solo URL firmada).
     * @param  string|null  $to  destinatario del canal.
     * @param  string|null  $message  mensaje; si es null se usa la URL firmada.
     */
    public function __construct(
        public readonly string $path,
        public readonly string $filename,
        public readonly ?string $channel = null,
        public readonly ?string $to = null,
        public readonly ?string $message = null,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(ReportStorageService $storage): void
    {
        $url = $storage->url($this->path);

        if ($this->channel === null || $this->to === null) {
            return;
        }

        SendChannelMessageJob::dispatch(
            $this->channel,
            $this->to,
            $this->message ?? "Su reporte está listo: {$url}",
        );
    }
}
