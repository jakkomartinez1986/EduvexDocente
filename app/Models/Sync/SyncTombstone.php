<?php

namespace App\Models\Sync;

use Database\Factories\Sync\SyncTombstoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tombstone de sincronización: marca una entidad eliminada en el servidor
 * para que el pull incremental del cliente pueda borrarla de su DB local.
 *
 * @property int $id
 * @property string $entity
 * @property int $entity_id
 * @property int|null $owner_user_id
 * @property Carbon $deleted_at
 */
#[Fillable(['entity', 'entity_id', 'owner_user_id', 'deleted_at'])]
class SyncTombstone extends Model
{
    /** @use HasFactory<SyncTombstoneFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }
}
