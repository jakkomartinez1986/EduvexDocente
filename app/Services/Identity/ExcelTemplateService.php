<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Exports\RepresentativesTemplateExport;
use App\Exports\StudentsTemplateExport;
use App\Exports\TeachersTemplateExport;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use RuntimeException;
use Throwable;

/**
 * Fuente única de verdad de las plantillas Excel de importación de identidad
 * (docentes, estudiantes y representantes). Cada plantilla se materializa en
 * storage/app/templates/{archivo} —el mismo directorio del que las sirve la
 * descarga— y se regenera bajo demanda cuando falta, de modo que un clon
 * limpio (storage/app está en .gitignore) no necesita `php artisan
 * templates:generate` para que el botón "Descargar Plantilla" funcione.
 */
class ExcelTemplateService
{
    public const DIRECTORY = 'templates';

    /**
     * @param  string|null  $directory  directorio destino; por defecto
     *                                  storage/app/templates. Las pruebas inyectan
     *                                  uno temporal para no pisar las plantillas reales.
     */
    public function __construct(private readonly ?string $directory = null) {}

    /**
     * Tipos soportados por la descarga: archivo en disco, nombre base para el
     * download y export de Maatwebsite que define encabezados y filas ejemplo.
     *
     * @var array<string, array{file: string, export: class-string}>
     */
    public const TYPES = [
        'estudiantes' => [
            'file' => 'plantilla_estudiantes.xlsx',
            'export' => StudentsTemplateExport::class,
        ],
        'docentes' => [
            'file' => 'plantilla_docentes.xlsx',
            'export' => TeachersTemplateExport::class,
        ],
        'representantes' => [
            'file' => 'plantilla_representantes.xlsx',
            'export' => RepresentativesTemplateExport::class,
        ],
    ];

    public function supports(string $type): bool
    {
        return array_key_exists($type, self::TYPES);
    }

    /**
     * @return array<int, string>
     */
    public function types(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * Ruta absoluta de la plantilla, exista o no en disco.
     */
    public function path(string $type): string
    {
        $this->guard($type);

        return $this->directory().DIRECTORY_SEPARATOR.self::TYPES[$type]['file'];
    }

    public function exists(string $type): bool
    {
        return $this->supports($type) && File::exists($this->path($type));
    }

    /**
     * Nombre sugerido en la descarga, sin extensión.
     */
    public function baseName(string $type): string
    {
        $this->guard($type);

        return pathinfo(self::TYPES[$type]['file'], PATHINFO_FILENAME);
    }

    /**
     * Nombre de descarga con sufijo de versión: plantilla_docentes_2026-09-29.xlsx
     */
    public function downloadName(string $type, string $suffix = ''): string
    {
        $base = $this->baseName($type);

        return $suffix === '' ? "{$base}.xlsx" : "{$base}_{$suffix}.xlsx";
    }

    /**
     * Devuelve la ruta de la plantilla generándola si aún no está en disco.
     * Es el punto de entrada del flujo de descarga: nunca devuelve una ruta
     * inexistente salvo que la escritura falle, en cuyo caso lanza.
     */
    public function ensure(string $type): string
    {
        $path = $this->path($type);

        if (! File::exists($path)) {
            $this->generate($type);
        }

        return $path;
    }

    /**
     * Escribe la plantilla en disco (sobrescribe la existente) y devuelve su ruta.
     *
     * @throws RuntimeException cuando el archivo no se puede escribir, lo que en
     *                          Windows ocurre si la plantilla está abierta en Excel.
     */
    public function generate(string $type): string
    {
        $this->guard($type);

        $path = $this->path($type);

        File::ensureDirectoryExists(dirname($path));

        try {
            File::put($path, ExcelFacade::raw($this->export($type), Excel::XLSX));
        } catch (Throwable $e) {
            throw new RuntimeException(
                "No se pudo escribir la plantilla \"{$path}\". Si está abierta en Excel, ciérrala e inténtalo de nuevo.",
                previous: $e
            );
        }

        return $path;
    }

    /**
     * Genera todas las plantillas soportadas.
     *
     * @return array<int, string> rutas absolutas escritas.
     */
    public function generateAll(): array
    {
        return array_map(fn (string $type): string => $this->generate($type), $this->types());
    }

    /**
     * @return FromArray&WithHeadings
     */
    private function export(string $type): object
    {
        $class = self::TYPES[$type]['export'];

        return new $class;
    }

    private function directory(): string
    {
        return $this->directory ?? storage_path('app'.DIRECTORY_SEPARATOR.self::DIRECTORY);
    }

    private function guard(string $type): void
    {
        abort_unless($this->supports($type), 404, "Plantilla desconocida: {$type}");
    }
}
