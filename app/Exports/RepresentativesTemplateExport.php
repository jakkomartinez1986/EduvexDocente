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
            ['MARY SAAD', 'BOUKMAN SANZ', '1710000017', 'maria.representante@ejemplo.com', '0900000001', '0962858401', 'PARROQUIA TOACASO CALLE MANA', '0500000112', 'MADRE', 'ENFERMERA', '0321234567'],
            ['ERIK JAVIER', 'SANZ BOUKMAN', '1710000025', 'erik.representante@ejemplo.com', '0900000002', '0900000002', 'SAQUISILI CALLE CHIMBORAZO', '0500000120', 'PADRE', 'ENFERMERO', '0321234568'],
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
