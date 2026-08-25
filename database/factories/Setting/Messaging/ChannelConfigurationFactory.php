<?php

namespace Database\Factories\Setting\Messaging;

use App\Models\Setting\Messaging\ChannelConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelConfiguration>
 */
class ChannelConfigurationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel' => fake()->unique()->randomElement(ChannelConfiguration::channels()),
            'provider' => 'meta_cloud',
            'enabled' => false,
            'credentials' => null,
            'sender_name' => fake()->company(),
            'test_destination' => null,
            'test_status' => 'pending',
        ];
    }

    public function whatsapp(): static
    {
        return $this->state(fn () => [
            'channel' => ChannelConfiguration::CHANNEL_WHATSAPP,
            'provider' => 'meta_cloud',
            'credentials' => ['token' => 'wa-token', 'phone_number_id' => '123456'],
        ]);
    }

    public function telegram(): static
    {
        return $this->state(fn () => [
            'channel' => ChannelConfiguration::CHANNEL_TELEGRAM,
            'provider' => 'telegram_bot',
            'credentials' => ['bot_token' => 'tg-token'],
        ]);
    }

    public function email(): static
    {
        return $this->state(fn () => [
            'channel' => ChannelConfiguration::CHANNEL_EMAIL,
            'provider' => 'smtp',
            'credentials' => null,
        ]);
    }

    public function enabled(): static
    {
        return $this->state(fn () => ['enabled' => true, 'test_status' => 'ok']);
    }
}
