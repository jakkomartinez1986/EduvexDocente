<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Persistencia y entrega de reportes generados (PDF/Excel) sobre el disco
 * configurado por FILESYSTEM_DISK (storage-strategy.md §4). Centraliza la
 * escritura en el Object Storage (local en dev, r2/s3 en Cloud vía C-05) y la
 * resolución de URLs firmadas para que los controllers y los jobs async
 * compartan un único punto de verdad.
 */
class ReportStorageService
{
    /**
     * Guarda el contenido en una ruta bajo el subdirectorio del tipo de reporte.
     *
     * @param  string  $contents  bytes del archivo.
     * @param  string  $type  subdirectorio bajo reports/ (p. ej. incidents).
     * @param  string  $filename  nombre del archivo (con extensión).
     */
    public function store(string $contents, string $type, string $filename): string
    {
        $path = 'reports/'.$type.'/'.$filename;

        $this->disk()->put($path, $contents);

        return $path;
    }

    /**
     * URL accesible del archivo. En discos con URLs firmadas (s3/r2) devuelve
     * una firma temporal; en discos locales/publicos cae a la URL servida.
     *
     * @param  string  $path  clave devuelta por store().
     */
    public function url(string $path, ?\DateTimeInterface $expiry = null): string
    {
        $driver = config('filesystems.disks.'.config('filesystems.default').'.driver', 'local');

        if ($driver === 's3') {
            return $this->disk()->temporaryUrl($path, $expiry ?? now()->addMinutes(10));
        }

        return $this->disk()->url($path);
    }

    private function disk(): Filesystem
    {
        return Storage::disk();
    }
}
