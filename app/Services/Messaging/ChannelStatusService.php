<?php

namespace App\Services\Messaging;

use App\Models\Setting\Messaging\ChannelConfiguration;
use App\Services\SchoolConfigService;
use Illuminate\Support\Facades\Cache;

class ChannelStatusService
{
    /**
     * Channels that support manual sending when the API is not configured.
     *
     * @var array<int, string>
     */
    public const MANUAL_CHANNELS = [
        ChannelConfiguration::CHANNEL_WHATSAPP,
    ];

    /**
     * Credentials required per channel for API sending.
     *
     * @var array<string, array<int, string>>
     */
    protected const REQUIRED_CREDENTIALS = [
        ChannelConfiguration::CHANNEL_WHATSAPP => ['token', 'phone_number_id'],
        ChannelConfiguration::CHANNEL_TELEGRAM => ['bot_token'],
    ];

    /**
     * TTL breve del status de un canal (30 s–1 min según cache-strategy.md §3).
     */
    private const STATUS_TTL_SECONDS = 60;

    /**
     * Build the availability matrix for the given channels.
     * Never exposes credentials or secrets, only booleans.
     *
     * @param  array<int, string>  $channels
     * @return array<string, array{enabled: bool, api_available: bool, manual_available: bool}>
     */
    public function forChannels(array $channels): array
    {
        $statuses = [];

        foreach ($channels as $channel) {
            $statuses[$channel] = $this->status($channel);
        }

        return $statuses;
    }

    /**
     * Status de un canal (cacheados 60 s). Regeneración protegida con lock
     * anti-stampede; solo se cachean primitivos booleans, nunca credenciales.
     *
     * @return array{enabled: bool, api_available: bool, manual_available: bool}
     */
    public function status(string $channel): array
    {
        $key = self::statusKey($channel);

        return Cache::remember(
            $key,
            now()->addSeconds(self::STATUS_TTL_SECONDS),
            function () use ($key, $channel): array {
                return Cache::lock($key.':lock', 30)
                    ->block(10, fn (): array => $this->computeStatus($channel));
            },
        );
    }

    /**
     * Whether the channel can be sent through its provider API right now:
     * enabled in DB AND all required credentials present (not just defined in .env).
     */
    public function apiAvailable(string $channel): bool
    {
        return $this->status($channel)['api_available'];
    }

    /**
     * Clave de caché de un canal. `{schoolId}` se resuelve vía SchoolConfigService
     * (cache 24 h); los tests sin escuela usan la variante `none`.
     */
    public static function statusKey(string $channel): string
    {
        $schoolId = app(SchoolConfigService::class)->getActiveSchoolId();

        return 'eduvex:'.app()->environment().':messaging:channel-status:'.($schoolId ?? 'none').':'.$channel;
    }

    public static function forget(string $channel): void
    {
        Cache::forget(self::statusKey($channel));
    }

    /**
     * @return array{enabled: bool, api_available: bool, manual_available: bool}
     */
    protected function computeStatus(string $channel): array
    {
        $config = $this->configuration($channel);

        return [
            'enabled' => $config !== null && $config->enabled,
            'api_available' => $this->available($channel, $config),
            'manual_available' => in_array($channel, self::MANUAL_CHANNELS, true),
        ];
    }

    protected function available(string $channel, ?ChannelConfiguration $config): bool
    {
        if ($config === null || ! $config->enabled) {
            return false;
        }

        $required = self::REQUIRED_CREDENTIALS[$channel] ?? null;

        if ($required === null) {
            return true;
        }

        foreach ($required as $key) {
            if (blank($config->credentials[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    protected function configuration(string $channel): ?ChannelConfiguration
    {
        /** @var ChannelConfiguration|null */
        return ChannelConfiguration::query()->where('channel', $channel)->first();
    }
}
