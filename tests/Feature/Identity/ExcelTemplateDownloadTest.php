<?php

use App\Models\User;
use App\Services\Identity\ExcelTemplateService;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Inyecta el servicio apuntando a un directorio temporal para que las pruebas
 * no pisen las plantillas reales de storage/app/templates.
 */
function templateService(string $directory): ExcelTemplateService
{
    File::ensureDirectoryExists($directory);

    return new ExcelTemplateService($directory);
}

/**
 * Contenido completo de la hoja activa del .xlsx generado. Las celdas vacías
 * vuelven como null desde el XLSX y se normalizan a '' para comparar con el
 * export que las produjo.
 *
 * @return array<int, array<int, string>>
 */
function readTemplateRows(string $path): array
{
    $sheet = IOFactory::load($path)->getActiveSheet();

    $rows = $sheet->rangeToArray(
        'A1:'.$sheet->getHighestColumn().$sheet->getHighestRow()
    );

    return array_map(
        fn (array $row): array => array_map(fn ($value): string => (string) $value, $row),
        $rows
    );
}

beforeEach(function (): void {
    $this->templateDirectory = storage_path('framework/testing/excel-templates');

    File::deleteDirectory($this->templateDirectory);

    $this->templates = templateService($this->templateDirectory);
});

afterEach(function (): void {
    File::deleteDirectory($this->templateDirectory);
});

it('expone los tres tipos de plantilla de identidad', function (): void {
    expect($this->templates->types())->toBe(['estudiantes', 'docentes', 'representantes'])
        ->and($this->templates->supports('docentes'))->toBeTrue()
        ->and($this->templates->supports('inventados'))->toBeFalse();
});

it('genera las tres plantillas en el directorio de descarga con los encabezados esperados', function (): void {
    $this->templates->generateAll();

    expect(readTemplateRows($this->templates->path('docentes'))[0])->toBe([
        'NOMBRES', 'APELLIDOS', 'DNI', 'EMAIL', 'TELEFONO', 'CELULAR',
        'DIRECCION', 'ESPECIALIZACION', 'TITULO', 'NIVEL_EDUCATIVO', 'FECHA_INGRESO',
    ]);

    expect(readTemplateRows($this->templates->path('estudiantes'))[0])->toBe([
        'NOMBRES', 'APELLIDOS', 'DNI', 'EMAIL', 'TELEFONO', 'CELULAR',
        'DIRECCION', 'FECHA_NACIMIENTO', 'TIPO_SANGRE', 'CONTACTO_EMERGENCIA', 'INFO_MEDICA',
    ]);

    expect(readTemplateRows($this->templates->path('representantes'))[0])->toBe([
        'NOMBRES', 'APELLIDOS', 'DNI', 'EMAIL', 'TELEFONO', 'CELULAR',
        'DIRECCION', 'DNI_ESTUDIANTE', 'PARENTESCO', 'OCUPACION', 'TELEFONO_LABORAL',
    ]);
});

it('incluye las filas de ejemplo declaradas en cada export', function (): void {
    $this->templates->generateAll();

    foreach (ExcelTemplateService::TYPES as $type => $definition) {
        $rows = readTemplateRows($this->templates->path($type));
        $samples = (new $definition['export'])->array();

        expect(array_slice($rows, 1, count($samples)))->toBe($samples);
    }
});

it('rechaza un tipo de plantilla desconocido con 404', function (): void {
    expect(fn () => $this->templates->path('inventados'))
        ->toThrow(NotFoundHttpException::class);
});

it('no reescribe una plantilla que ya existe en disco', function (): void {
    $path = $this->templates->generate('docentes');
    $original = File::get($path);

    touch($path, now()->subDay()->getTimestamp());

    expect($this->templates->ensure('docentes'))->toBe($path)
        ->and(File::get($path))->toBe($original);
});

it('reporta con claridad cuando la plantilla no se puede escribir', function (): void {
    File::ensureDirectoryExists($this->templates->path('docentes'));

    expect(fn () => $this->templates->generate('docentes'))
        ->toThrow(RuntimeException::class, 'Si está abierta en Excel, ciérrala');
});

it('compone el nombre de descarga con el sufijo de fecha', function (): void {
    expect($this->templates->downloadName('docentes', '2026-09-29'))->toBe('plantilla_docentes_2026-09-29.xlsx')
        ->and($this->templates->downloadName('estudiantes'))->toBe('plantilla_estudiantes.xlsx');
});

it('la ruta de descarga genera la plantilla bajo demanda cuando falta en disco', function (): void {
    $this->app->instance(ExcelTemplateService::class, $this->templates);

    expect(File::exists($this->templates->path('docentes')))->toBeFalse();

    $this->actingAs(User::factory()->create())
        ->get(route('system.identity.templates.download', 'docentes'))
        ->assertOk()
        ->assertDownload('plantilla_docentes_'.now()->toDateString().'.xlsx');

    expect(File::exists($this->templates->path('docentes')))->toBeTrue();
});

it('la ruta de descarga responde 404 para un tipo no soportado', function (): void {
    $this->app->instance(ExcelTemplateService::class, $this->templates);

    $this->actingAs(User::factory()->create())
        ->get('/system/identity/templates/inventados/download')
        ->assertNotFound();
});

it('la ruta de descarga exige autenticación', function (): void {
    $this->app->instance(ExcelTemplateService::class, $this->templates);

    $this->get(route('system.identity.templates.download', 'estudiantes'))
        ->assertRedirect(route('login'));
});

it('el comando templates:generate escribe las tres plantillas en el directorio del servicio', function (): void {
    $this->app->instance(ExcelTemplateService::class, $this->templates);

    $this->artisan('templates:generate')
        ->expectsOutputToContain('Plantilla generada: '.$this->templates->path('docentes'))
        ->assertSuccessful();

    foreach (ExcelTemplateService::TYPES as $type => $definition) {
        $rows = readTemplateRows($this->templates->path($type));

        expect($rows[0])->toBe((new $definition['export'])->headings())
            ->and(File::exists($this->templateDirectory.'/'.$definition['file']))->toBeTrue();
    }
});
