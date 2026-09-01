<?php

declare(strict_types=1);

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

/**
 * Invalida la caché global de permisos de Spatie cuando un rol o permiso
 * cambia (created/updated/deleted). Sin esta invalidación push, la caché
 * expira cada 24 h y los roles recién asignados tardan en reflejarse
 * (hallazgo B-11 de la auditoría).
 */
class PermissionCacheObserver
{
    protected PermissionRegistrar $registrar;

    public function __construct(PermissionRegistrar $registrar)
    {
        $this->registrar = $registrar;
    }

    public function saved(Model $model): void
    {
        $this->registrar->forgetCachedPermissions();
    }

    public function deleted(Model $model): void
    {
        $this->registrar->forgetCachedPermissions();
    }
}
