<?php

namespace App\Services\Messaging;

use App\Models\Setting\Messaging\ChannelConfiguration;

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
     * Build the availability matrix for the given channels.
     * Never exposes credentials or secrets, only booleans.
     *
     * @param  array<int, string>  $channels
     * @return array<string, array{enabled: bool, api_available: bool, manual_available: bool}>
     */
    public function forChannels(array $channels): array
    {
        $configs = ChannelConfiguration::query()
            ->whereIn('channel', $channels)
            ->get()
            ->keyBy('channel');

        $statuses = [];

        foreach ($channels as $channel) {
            $config = $configs->get($channel);

            $statuses[$channel] = [
                'enabled' => $config !== null && $config->enabled,
                'api_available' => $this->available($channel, $config),
                'manual_available' => in_array($channel, self::MANUAL_CHANNELS, true),
            ];
        }

        return $statuses;
    }

    /**
     * Whether the channel can be sent through its provider API right now:
     * enabled in DB AND all required credentials present (not just defined in .env).
     */
    public function apiAvailable(string $channel): bool
    {
        return $this->available($channel, $this->configuration($channel));
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
