<?php

namespace App\Services\Messaging;

use App\Models\Setting\Messaging\ChannelConfiguration;
use App\Services\Messaging\Contracts\ChannelSender;
use App\Services\SchoolConfigService;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class MessagingManager
{
    /**
     * TTL del snapshot de credenciales descifradas (15 min, cache-strategy.md §3).
     */
    private const CREDS_TTL_MINUTES = 15;

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
        $snapshot = Cache::remember(
            self::credsKey($channel),
            now()->addMinutes(self::CREDS_TTL_MINUTES),
            fn (): ?array => $this->configurationSnapshot($channel),
        );

        if ($snapshot === null || ! $snapshot['enabled']) {
            throw new InvalidArgumentException("El canal [{$channel}] no está habilitado.");
        }

        $credentials = $credentialsOverride ?? $snapshot['credentials'];

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
     * Snapshot primitivo de un canal: enabled + credenciales descifradas.
     * Nunca se cachea el modelo Eloquent (evita __PHP_Incomplete_Class).
     *
     * @return array{enabled: bool, credentials: array<string, mixed>}|null
     */
    protected function configurationSnapshot(string $channel): ?array
    {
        $configuration = ChannelConfiguration::query()->where('channel', $channel)->first();

        if ($configuration === null) {
            return null;
        }

        return [
            'enabled' => $configuration->enabled,
            'credentials' => $configuration->credentials ?? [],
        ];
    }

    /**
     * Clave de caché del snapshot de credenciales. `{schoolId}` se resuelve vía
     * SchoolConfigService (cache 24 h); los tests sin escuela usan `none`.
     */
    public static function credsKey(string $channel): string
    {
        $schoolId = app(SchoolConfigService::class)->getActiveSchoolId();

        return 'eduvex:'.app()->environment().':messaging:creds:'.($schoolId ?? 'none').':'.$channel;
    }

    public static function forgetChannel(string $channel): void
    {
        Cache::forget(self::credsKey($channel));
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
