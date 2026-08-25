<?php

namespace App\Services\Messaging;

use App\Services\Messaging\Contracts\ChannelSender;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramSender implements ChannelSender
{
    public function __construct(
        private readonly string $botToken,
    ) {}

    public function send(string $to, string $message, ?string $pdfPath = null, ?string $pdfName = null): SendResult
    {
        try {
            if ($pdfPath !== null && is_file($pdfPath)) {
                return $this->sendDocument($to, $message, $pdfPath, $pdfName);
            }

            $response = $this->client()->post($this->method('sendMessage'), [
                'chat_id' => $to,
                'text' => $message,
            ]);

            return $this->toResult($response);
        } catch (\Throwable $e) {
            Log::warning('Telegram send failed', ['error' => $e->getMessage()]);

            return SendResult::fail($e->getMessage());
        }
    }

    public function verify(): SendResult
    {
        try {
            $response = $this->client()->get($this->method('getMe'));

            if ($response->successful() && $response->json('ok') === true) {
                return SendResult::ok((string) $response->json('result.id'));
            }

            return SendResult::fail((string) $response->json('description', 'HTTP '.$response->status()));
        } catch (\Throwable $e) {
            return SendResult::fail($e->getMessage());
        }
    }

    private function sendDocument(string $to, string $message, string $pdfPath, ?string $pdfName): SendResult
    {
        $request = $this->client()
            ->attach('document', (string) file_get_contents($pdfPath), $pdfName ?? basename($pdfPath))
            ->post($this->method('sendDocument'), [
                'chat_id' => $to,
                'caption' => $message,
            ]);

        return $this->toResult($request);
    }

    private function client(): PendingRequest
    {
        return Http::asJson()->baseUrl('');
    }

    private function method(string $name): string
    {
        return 'https://api.telegram.org/bot'.$this->botToken.'/'.$name;
    }

    /**
     * @param  Response  $response
     */
    private function toResult($response): SendResult
    {
        if ($response->json('ok') === true) {
            $result = $response->json();

            return SendResult::ok((string) data_get($result, 'result.message_id'));
        }

        return SendResult::fail((string) $response->json('description', 'HTTP '.$response->status()));
    }
}
