<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RepresentativesTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['MARY SAAD', 'BOUKMAN SANZ', '050000001', 'mark@ejemplo.com', '0900000001', '0962858401', 'PARROQUIA TOACASO CALLE MANA', '1700000001', 'MADRE', 'ENFERMERA', '032123456'],
            ['ERIK JAVIER', 'SANZ BOUKMAN', '050000002', 'erik@ejemplo.com', '0900000002', '0900000002', 'SAQUISILI CALLE CHIBORAZO', '1700000002', 'PADRE', 'ENFERMERO', '032123457'],
        ];
    }

    public function headings(): array
    {
        return [
            'NOMBRES', 'APELLIDOS', 'DNI', 'EMAIL',
            'TELEFONO', 'CELULAR', 'DIRECCION',
            'DNI_ESTUDIANTE', 'PARENTESCO', 'OCUPACION', 'TELEFONO_LABORAL',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
