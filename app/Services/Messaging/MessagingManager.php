<?php

namespace App\Services\Messaging;

use App\Models\Setting\Messaging\ChannelConfiguration;
use App\Services\Messaging\Contracts\ChannelSender;
use InvalidArgumentException;

class MessagingManager
{
    /**
     * @param  array<string, mixed>|null  $credentialsOverride
     */
    public function send(string $channel, string $to, string $message, ?string $pdfPath = null, ?string $pdfName = null, ?array $credentialsOverride = null): SendResult
    {
        try {
            return $this->for($channel, $credentialsOverride)->send($to, $message, $pdfPath, $pdfName);
        } catch (InvalidArgumentException $e) {
            return SendResult::fail($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>|null  $credentialsOverride
     */
    public function verify(string $channel, ?array $credentialsOverride = null): SendResult
    {
        try {
            return $this->for($channel, $credentialsOverride)->verify();
        } catch (InvalidArgumentException $e) {
            return SendResult::fail($e->getMessage());
        }
    }

    public function isEnabled(string $channel): bool
    {
        return ChannelConfiguration::query()
            ->where('channel', $channel)
            ->where('enabled', true)
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    public function enabledChannels(): array
    {
        return ChannelConfiguration::query()
            ->where('enabled', true)
            ->orderBy('channel')
            ->pluck('channel')
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $credentialsOverride
     */
    public function for(string $channel, ?array $credentialsOverride = null): ChannelSender
    {
        $configuration = ChannelConfiguration::query()->where('channel', $channel)->first();

        if (! $configuration || ! $configuration->enabled) {
            throw new InvalidArgumentException("El canal [{$channel}] no está habilitado.");
        }

        $credentials = $credentialsOverride ?? $configuration->credentials ?? [];

        return match ($channel) {
            ChannelConfiguration::CHANNEL_WHATSAPP => new WhatsAppCloudSender(
                (string) ($credentials['token'] ?? ''),
                (string) ($credentials['phone_number_id'] ?? ''),
            ),
            ChannelConfiguration::CHANNEL_TELEGRAM => new TelegramSender(
                (string) ($credentials['bot_token'] ?? ''),
            ),
            ChannelConfiguration::CHANNEL_EMAIL => new EmailSender,
            default => throw new InvalidArgumentException("Canal no soportado [{$channel}]."),
        };
    }

    /**
     * @return array<string, array{label: string, fields: array<string, string>}>
     */
    public static function providerFields(): array
    {
        return [
            ChannelConfiguration::CHANNEL_WHATSAPP => [
                'label' => 'WhatsApp Cloud API',
                'fields' => ['token' => 'Token permanente', 'phone_number_id' => 'Phone Number ID'],
            ],
            ChannelConfiguration::CHANNEL_TELEGRAM => [
                'label' => 'Telegram Bot API',
                'fields' => ['bot_token' => 'Token del bot (@BotFather)'],
            ],
            ChannelConfiguration::CHANNEL_EMAIL => [
                'label' => 'Correo (SMTP configurado)',
                'fields' => [],
            ],
        ];
    }
}
