<?php

namespace App\Jobs;

use App\Models\Incidents\NotificationChannel;
use App\Services\Messaging\MessagingManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envío de mensajería por canal (whatsapp/telegram/email).
 *
 * Robustez (H-04): el job es único por canal+destino+mensaje (evita envíos
 * duplicados por doble clic), con reintentos con backoff exponencial y timeout
 * amplio para redes externas, enrutado a la cola `notifications`.
 */
class SendChannelMessageJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120, 240];

    public int $timeout = 180;

    public function __construct(
        public readonly string $channel,
        public readonly string $to,
        public readonly string $message,
        public readonly ?string $pdfPath = null,
        public readonly ?string $pdfName = null,
        public readonly ?int $notificationChannelId = null,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Clave de unicidad: no enviar dos veces el mismo contenido al mismo
     * destinatario en una ventana corta (dedupe de envíos duplicados).
     */
    public function uniqueId(): string
    {
        return "{$this->channel}:{$this->to}:{$this->message}";
    }

    public function handle(MessagingManager $messaging): void
    {
        $result = $messaging->send($this->channel, $this->to, $this->message, $this->pdfPath, $this->pdfName);

        $this->updateChannelStatus($result->success ? 'sent' : 'failed');

        if (! $result->success) {
            Log::warning('Envío de canal falló', [
                'channel' => $this->channel,
                'to' => $this->to,
                'error' => $result->error,
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->notificationChannelId !== null) {
            $this->updateChannelStatus('failed');
        }

        Log::error('Envío de canal agotó reintentos', [
            'channel' => $this->channel,
            'to' => $this->to,
            'exception' => $exception?->getMessage(),
        ]);
    }

    private function updateChannelStatus(string $status): void
    {
        if ($this->notificationChannelId === null) {
            return;
        }

        NotificationChannel::query()
            ->where('id', $this->notificationChannelId)
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'sent_at' => now(),
            ]);
    }
}
