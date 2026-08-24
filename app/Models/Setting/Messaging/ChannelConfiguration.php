<?php

namespace App\Models\Setting\Messaging;

use Database\Factories\Setting\Messaging\ChannelConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $channel
 * @property string $provider
 * @property bool $enabled
 * @property array<string, mixed>|null $credentials
 * @property string|null $sender_name
 * @property string|null $test_destination
 * @property string $test_status
 * @property Carbon|null $tested_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'channel', 'provider', 'enabled', 'credentials', 'sender_name',
    'test_destination', 'test_status', 'tested_at', 'last_error',
])]
class ChannelConfiguration extends Model
{
    /** @use HasFactory<ChannelConfigurationFactory> */
    use HasFactory;

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_TELEGRAM = 'telegram';

    public const CHANNEL_EMAIL = 'email';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'tested_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function channels(): array
    {
        return [self::CHANNEL_WHATSAPP, self::CHANNEL_TELEGRAM, self::CHANNEL_EMAIL];
    }
}
