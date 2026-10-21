<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentsTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['MARY SAAD', 'BOUKMAN SANZ', '0500000112', 'mary.estudiante@ejemplo.com', '0900000001', '0962858401', 'PARROQUIA TOACASO CALLE MANA', '2015-03-15', 'O+', 'MARIA PEREZ - 0991234567', ''],
            ['ERIK JAVIER', 'SANZ BOUKMAN', '0500000120', 'erik.estudiante@ejemplo.com', '0900000002', '0900000002', 'SAQUISILI CALLE CHIMBORAZO', '2016-07-20', 'A+', 'JOSE LOPEZ - 0997654321', 'ALERGIA A LA PENICILINA'],
        ];
    }

    public function headings(): array
    {
        return [
            'NOMBRES', 'APELLIDOS', 'DNI', 'EMAIL',
            'TELEFONO', 'CELULAR', 'DIRECCION',
            'FECHA_NACIMIENTO', 'TIPO_SANGRE', 'CONTACTO_EMERGENCIA', 'INFO_MEDICA',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
