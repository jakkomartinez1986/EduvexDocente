<?php

namespace App\Jobs;

use App\Models\Incidents\NotificationChannel;
use App\Services\Messaging\MessagingManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendChannelMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $channel,
        public readonly string $to,
        public readonly string $message,
        public readonly ?string $pdfPath = null,
        public readonly ?string $pdfName = null,
        public readonly ?int $notificationChannelId = null,
    ) {}

    public function handle(MessagingManager $messaging): void
    {
        $result = $messaging->send($this->channel, $this->to, $this->message, $this->pdfPath, $this->pdfName);

        if ($this->notificationChannelId !== null) {
            NotificationChannel::query()
                ->where('id', $this->notificationChannelId)
                ->update([
                    'status' => $result->success ? 'sent' : 'failed',
                    'sent_at' => now(),
                ]);
        }

        if (! $result->success) {
            Log::warning('Envío de canal falló', [
                'channel' => $this->channel,
                'to' => $this->to,
                'error' => $result->error,
            ]);
        }
    }
}
