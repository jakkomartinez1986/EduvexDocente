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
     * Comprueba si el reporte ya está persistido (para el flujo async: si el
     * archivo existe se sirve la URL firmada sin re-encolar la generación).
     */
    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    /**
     * URL accesible del archivo. En discos s3/r2 y en discos locales con el
     * driver `serve` habilitado devuelve una URL firmada temporal; en discos
     * locales/publicos sin servir, la URL servida plana.
     *
     * @param  string  $path  clave devuelta por store().
     */
    public function url(string $path, ?\DateTimeInterface $expiry = null): string
    {
        $diskConfig = config('filesystems.disks.'.config('filesystems.default'), []);
        $driver = $diskConfig['driver'] ?? 'local';
        $serve = (bool) ($diskConfig['serve'] ?? false);

        if ($driver === 's3' || $serve) {
            return $this->disk()->temporaryUrl($path, $expiry ?? now()->addMinutes(10));
        }

        return $this->disk()->url($path);
    }

    private function disk(): Filesystem
    {
        return Storage::disk();
    }
}
