<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Setting\Messaging\ChannelConfiguration;
use App\Services\Messaging\ChannelStatusService;
use App\Services\Messaging\MessagingManager;

/**
 * Invalida la caché del status y del snapshot de credenciales de un canal cuando
 * cambia su ChannelConfiguration (guardar/probar/eliminar). Ambos dependen del
 * cifrado almacenado; sin esta invalidación push, el descifrado y el estado de
 * envío tardarían hasta el TTL en reflejar los cambios (cache-strategy.md §3:
 * "Credenciales cifradas de canales" on save, "Status de canales" on send).
 */
class ChannelConfigurationCacheObserver
{
    public function saved(ChannelConfiguration $configuration): void
    {
        $this->forgetChannelCache($configuration);
    }

    public function deleted(ChannelConfiguration $configuration): void
    {
        $this->forgetChannelCache($configuration);
    }

    public function restored(ChannelConfiguration $configuration): void
    {
        $this->forgetChannelCache($configuration);
    }

    private function forgetChannelCache(ChannelConfiguration $configuration): void
    {
        ChannelStatusService::forget($configuration->channel);
        MessagingManager::forgetChannel($configuration->channel);
    }
}
