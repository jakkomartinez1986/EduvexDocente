<?php

namespace App\Services\Messaging;

use App\Services\Messaging\Contracts\ChannelSender;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudSender implements ChannelSender
{
    private const API_BASE = 'https://graph.facebook.com/v21.0';

    public function __construct(
        private readonly string $token,
        private readonly string $phoneNumberId,
    ) {}

    public function send(string $to, string $message, ?string $pdfPath = null, ?string $pdfName = null): SendResult
    {
        try {
            if ($pdfPath !== null && is_file($pdfPath)) {
                return $this->sendDocument($to, $message, $pdfPath, $pdfName);
            }

            $response = Http::withToken($this->token)
                ->post($this->endpoint((string) $this->phoneNumberId).'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            return $this->toResult($response);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp Cloud send failed', ['error' => $e->getMessage()]);

            return SendResult::fail($e->getMessage());
        }
    }

    public function verify(): SendResult
    {
        try {
            $response = Http::withToken($this->token)
                ->get($this->endpoint((string) $this->phoneNumberId));

            if ($response->successful()) {
                return SendResult::ok();
            }

            return SendResult::fail('HTTP '.$response->status().': '.(string) $response->json('error.message', $response->body()));
        } catch (\Throwable $e) {
            return SendResult::fail($e->getMessage());
        }
    }

    private function sendDocument(string $to, string $message, string $pdfPath, ?string $pdfName): SendResult
    {
        $upload = Http::withToken($this->token)
            ->attach('file', (string) file_get_contents($pdfPath), basename($pdfPath))
            ->post($this->endpoint((string) $this->phoneNumberId).'/media', [
                'messaging_product' => 'whatsapp',
                'type' => 'application/pdf',
            ]);

        if (! $upload->successful()) {
            return SendResult::fail('Media upload failed: HTTP '.$upload->status());
        }

        $mediaId = (string) $upload->json('id');

        if ($mediaId === '') {
            return SendResult::fail('Media upload failed: missing media id');
        }

        $response = Http::withToken($this->token)
            ->post($this->endpoint((string) $this->phoneNumberId).'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'document',
                'document' => [
                    'id' => $mediaId,
                    'filename' => $pdfName ?? basename($pdfPath),
                    'caption' => $message,
                ],
            ]);

        return $this->toResult($response);
    }

    /**
     * @param  Response  $response
     */
    private function toResult($response): SendResult
    {
        if ($response->successful()) {
            return SendResult::ok((string) $response->json('messages.0.id'));
        }

        return SendResult::fail('HTTP '.$response->status().': '.(string) $response->json('error.message', $response->body()));
    }

    private function endpoint(string $path): string
    {
        return self::API_BASE.'/'.$path;
    }
}
