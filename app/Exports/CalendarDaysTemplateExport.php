<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CalendarDaysTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['2025-09-01', 'Inicio de clases', 'NO', ''],
            ['2025-09-02', 'Clases regulares', 'NO', ''],
            ['2025-10-09', 'Dia de la Independencia de Latacunga', 'SI', ''],
            ['2025-11-03', 'Dia de los Difuntos', 'SI', ''],
            ['2025-11-25', 'Batalla de Cuaspud', 'SI', ''],
            ['2025-12-25', 'Navidad', 'SI', ''],
            ['2026-01-01', 'Ano Nuevo', 'SI', ''],
            ['2026-05-01', 'Dia del Trabajador', 'SI', ''],
            ['2026-05-24', 'Batalla de Pichincha', 'SI', ''],
            ['2026-06-26', 'Primer Grito de Independencia', 'SI', ''],
        ];
    }

    public function headings(): array
    {
        return [
            'FECHA', 'ACTIVIDAD', 'FERIADO', 'TRIMESTRE',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
