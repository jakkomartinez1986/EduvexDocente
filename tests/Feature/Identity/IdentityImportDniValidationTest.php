<?php

use App\Exports\RepresentativesTemplateExport;
use App\Exports\StudentsTemplateExport;
use App\Exports\TeachersTemplateExport;
use App\Imports\RepresentativesImport;
use App\Imports\StudentsImport;
use App\Imports\TeachersImport;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\User;
use App\Rules\FlexibleDni;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['DOCENTE', 'ESTUDIANTE', 'REPRESENTANTE'] as $name) {
        Role::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['description' => $name]
        );
    }
});

/**
 * Ejecuta la regla sobre un valor suelto y devuelve el primer mensaje de error.
 */
function dniError(mixed $value): ?string
{
    $error = Validator::make(['dni' => $value], ['dni' => ['required', new FlexibleDni]])
        ->errors()
        ->first('dni');

    return $error === '' ? null : $error;
}

/**
 * Convierte una fila de ejemplo de un export a las claves en minúsculas que
 * produce Maatwebsite con WithHeadingRow.
 *
 * @param  array<int, string>  $headings
 * @param  array<int, mixed>  $row
 * @return array<string, mixed>
 */
function headingRowToImportRow(array $headings, array $row): array
{
    return array_combine(
        array_map('mb_strtolower', $headings),
        array_map(fn ($v): string => (string) $v, array_pad($row, count($headings), ''))
    );
}

describe('FlexibleDni', function (): void {
    it('acepta una cédula ecuatoriana de 10 dígitos con módulo 10 válido', function (): void {
        foreach (['0500000013', '1710000017', '0501497390', '1721583092'] as $cedula) {
            expect(dniError($cedula))->toBeNull();
        }
    });

    it('rechaza una cédula de 9 dígitos a la que le falta el 0 inicial', function (): void {
        expect(dniError('050000001'))->toBe('El dni debe tener 10 dígitos; una cédula de 9 dígitos está incompleta.')
            ->and(dniError('17123456'))->toContain('8 dígitos está incompleta');
    });

    it('rechaza una cédula de 10 dígitos con dígito verificador inválido', function (): void {
        expect(dniError('0500000000'))->toBe('El dni de cédula ecuatoriana no es válido.')
            ->and(dniError('1000000000'))->toBe('El dni de cédula ecuatoriana no es válido.');
    });

    it('rechaza un código de provincia inexistente', function (): void {
        expect(dniError('9900000000'))->toBe('El dni no corresponde a una provincia válida.');
    });

    it('acepta la cédula de extranjero con prefijo E sin validar el dígito verificador', function (): void {
        expect(dniError('E1712345678'))->toBeNull()
            ->and(dniError('E12345678'))->toBeNull()
            ->and(dniError('e12345678'))->toBeNull();
    });

    it('rechaza letras que no sean el prefijo E de extranjero', function (): void {
        expect(dniError('ABC'))->toBe('El dni debe tener 10 dígitos, o iniciar con E si es una cédula de extranjero.')
            ->and(dniError('X1712345678'))->toBe('El dni debe tener 10 dígitos, o iniciar con E si es una cédula de extranjero.')
            ->and(dniError('050000001A'))->toBe('El dni debe tener 10 dígitos, o iniciar con E si es una cédula de extranjero.');
    });

    it('rechaza longitudes distintas de 10 dígitos', function (): void {
        expect(dniError('123456789'))->toContain('9 dígitos está incompleta')
            ->and(dniError('05000000130'))->toContain('11 dígitos está incompleta')
            ->and(dniError('1'))->toContain('1 dígitos está incompleta');
    });
});

describe('capa de previsualización', function (): void {
    it('marca como error la cédula de 9 dígitos y no crea el usuario', function (): void {
        $import = new TeachersImport(previewOnly: true);

        $import->collection(collect([headingRowToImportRow(
            (new TeachersTemplateExport)->headings(),
            ['MARY SAAD', 'BOUKMAN SANZ', '050000001', 'mary@ejemplo.com', '0900000001', '0962858401', 'TOACASO', 'MATEMATICAS', 'LICENCIADO', 'SUPERIOR', '2020-01-15']
        )]));

        expect($import->getErrorRows())->toBe(1)
            ->and($import->getValidRows())->toBe(0)
            ->and($import->getRows()[0]['errors'])->toContain('10 dígitos');
    });

    it('no persiste nada al confirmar una fila con cédula inválida', function (): void {
        $import = new TeachersImport(previewOnly: false);

        $import->collection(collect([headingRowToImportRow(
            (new TeachersTemplateExport)->headings(),
            ['MARY SAAD', 'BOUKMAN SANZ', '050000001', 'mary@ejemplo.com', '0900000001', '0962858401', 'TOACASO', 'MATEMATICAS', 'LICENCIADO', 'SUPERIOR', '2020-01-15']
        )]));

        expect(User::where('dni', '050000001')->exists())->toBeFalse()
            ->and(Teacher::count())->toBe(0);
    });

    it('acepta una cédula de extranjero con prefijo E en la previsualización', function (): void {
        $import = new TeachersImport(previewOnly: true);

        $import->collection(collect([headingRowToImportRow(
            (new TeachersTemplateExport)->headings(),
            ['JOHN DOE', 'SMITH', 'E1712345678', 'john@ejemplo.com', '0900000001', '0962858401', 'TOACASO', 'MATEMATICAS', 'LICENCIADO', 'SUPERIOR', '2020-01-15']
        )]));

        expect($import->getErrorRows())->toBe(0)
            ->and($import->getValidRows())->toBe(1);
    });

    it('detecta una cédula repetida dentro del mismo archivo', function (): void {
        $headings = (new TeachersTemplateExport)->headings();
        $row = headingRowToImportRow($headings, ['MARY SAAD', 'BOUKMAN SANZ', '0500000013', 'mary@ejemplo.com', '0900000001', '0962858401', 'TOACASO', 'MATEMATICAS', 'LICENCIADO', 'SUPERIOR', '2020-01-15']);
        $otro = [...$row, 'name' => 'OTRA PERSONA'];

        $import = new TeachersImport(previewOnly: false);

        $import->collection(collect([$row, $otro]));

        expect($import->getValidRows())->toBe(1)
            ->and($import->getErrorRows())->toBe(1)
            ->and($import->getRows()[1]['errors'])->toBe('DNI repetido en el archivo: ya aparece en la fila 2.')
            ->and(User::where('dni', '0500000013')->count())->toBe(1);
    });

    it('rechaza el DNI_ESTUDIANTE de 9 dígitos de la plantilla de representantes', function (): void {
        $import = new RepresentativesImport(previewOnly: true);

        $import->collection(collect([headingRowToImportRow(
            (new RepresentativesTemplateExport)->headings(),
            ['MARIA PEREZ', 'PEREZ PEREZ', '1710000017', 'maria@ejemplo.com', '0900000001', '0962858401', 'TOACASO', '050000011', 'MADRE', 'ENFERMERA', '0321234567']
        )]));

        expect($import->getErrorRows())->toBe(1)
            ->and($import->getRows()[0]['errors'])->toContain('10 dígitos');
    });
});

describe('filas de ejemplo de las plantillas', function (): void {
    /**
     * @return array<int, array<string, mixed>>
     */
    function sampleRows(object $export): array
    {
        return array_map(
            fn (array $row): array => headingRowToImportRow($export->headings(), $row),
            $export->array()
        );
    }

    it('las filas de docentes pasan la previsualización sin errores', function (): void {
        $import = new TeachersImport(previewOnly: true);
        $import->collection(collect(sampleRows(new TeachersTemplateExport)));

        expect($import->getRows())->each->not->toHaveKey('errors')
            ->and($import->getValidRows())->toBe(3);
    });

    it('las filas de estudiantes pasan la previsualización sin errores', function (): void {
        $import = new StudentsImport(previewOnly: true);
        $import->collection(collect(sampleRows(new StudentsTemplateExport)));

        expect($import->getRows())->each->not->toHaveKey('errors')
            ->and($import->getValidRows())->toBe(2);
    });

    it('las tres plantillas se importan en secuencia sin un solo error', function (): void {
        $gradeId = academicContext()['grade']->id;

        $teachers = new TeachersImport(previewOnly: false);
        $teachers->collection(collect(sampleRows(new TeachersTemplateExport)));

        $students = new StudentsImport(previewOnly: false, gradeId: $gradeId);
        $students->collection(collect(sampleRows(new StudentsTemplateExport)));

        $representatives = new RepresentativesImport(previewOnly: false);
        $representatives->collection(collect(sampleRows(new RepresentativesTemplateExport)));

        expect($teachers->getErrorRows())->toBe(0)
            ->and($students->getErrorRows())->toBe(0)
            ->and($representatives->getErrorRows())->toBe(0)
            ->and(Teacher::whereHas('user', fn ($q) => $q->whereIn('dni', ['0500000013', '0500000021', '0500000039']))->count())->toBe(3)
            ->and(Student::whereHas('user', fn ($q) => $q->whereIn('dni', ['0500000112', '0500000120']))->count())->toBe(2)
            ->and($representatives->getRows())->each->not->toHaveKey('errors');
    });
});

describe('representantes sin email', function (): void {
    it('genera un email provisional y no revienta el insert', function (): void {
        $context = academicContext();

        $student = Student::factory()->create(['user_id' => User::factory()->create([
            'dni' => '0500000112',
        ])->id]);

        $import = new RepresentativesImport(previewOnly: false);

        $import->collection(collect([headingRowToImportRow(
            (new RepresentativesTemplateExport)->headings(),
            ['MARIA PEREZ', 'PEREZ PEREZ', '1710000017', '', '0900000001', '0962858401', 'TOACASO', '0500000112', 'MADRE', 'ENFERMERA', '0321234567']
        )]));

        $user = User::where('dni', '1710000017')->first();

        expect($import->getErrorRows())->toBe(0)
            ->and($user)->not->toBeNull()
            ->and($user->email)->toEndWith('@educaplusrepresentante.edu.ec')
            ->and($user->email)->toBe('maria.perez.rep-0001@educaplusrepresentante.edu.ec');
    });
});
