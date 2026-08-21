<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TeachersTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['MARY SAAD', 'BOUKMAN SANZ', '050000001', 'mark@ejemplo.com', '0900000001', '0962858401', 'PARROQUIA TOACASO CALLE MANA', 'MATEMATICAS', 'LICENCIADO EN MATEMATICAS', 'SUPERIOR', '2020-01-15'],
            ['ERIK JAVIER', 'SANZ BOUKMAN', '050000002', 'erik@ejemplo.com', '0900000002', '0900000002', 'SAQUISILI CALLE CHIMBORAZO', 'CIENCIAS', 'MASTER EN EDUCACION Y CIENCIAS', 'POSGRADO', '2021-03-01'],
            ['JUAN JAVIER', 'ABFG SEI', '050000003', 'juan@ejemplo.com', '0900000003', '0900000003', 'LATACUNGA CALLE A Y CALLE B N4', 'LENGUA', 'DOCTOR EN CIENCIAS DE LA EDUCACION', 'DOCTORADO', '2021-03-01'],
        ];
    }

    public function headings(): array
    {
        return [
            'NOMBRES', 'APELLIDOS', 'DNI', 'EMAIL',
            'TELEFONO', 'CELULAR', 'DIRECCION',
            'ESPECIALIZACION', 'TITULO', 'NIVEL_EDUCATIVO', 'FECHA_INGRESO',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
