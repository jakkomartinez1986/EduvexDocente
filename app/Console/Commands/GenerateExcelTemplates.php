<?php

namespace App\Console\Commands;

use App\Exports\RepresentativesTemplateExport;
use App\Exports\StudentsTemplateExport;
use App\Exports\TeachersTemplateExport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class GenerateExcelTemplates extends Command
{
    protected $signature = 'templates:generate';

    protected $description = 'Generar plantillas Excel para importacion de datos';

    public function handle(): int
    {
        $templates = [
            'plantilla_estudiantes.xlsx' => new StudentsTemplateExport,
            'plantilla_docentes.xlsx' => new TeachersTemplateExport,
            'plantilla_representantes.xlsx' => new RepresentativesTemplateExport,
        ];

        $templateDir = storage_path('app/templates');
        $tempDir = sys_get_temp_dir();

        if (! is_dir($templateDir)) {
            mkdir($templateDir, 0755, true);
        }

        foreach ($templates as $filename => $export) {
            $targetPath = $templateDir.'/'.$filename;
            $tempFile = $tempDir.DIRECTORY_SEPARATOR.'xlsx_'.uniqid().'.xlsx';

            Excel::store($export, basename($tempFile), 'local');

            $tempPath = storage_path('app/private/'.basename($tempFile));
            if (file_exists($tempPath)) {
                if (file_exists($targetPath)) {
                    @unlink($targetPath);
                }
                copy($tempPath, $targetPath);
                @unlink($tempPath);
            }

            $this->info("Plantilla generada: {$targetPath}");
        }

        $this->info('Todas las plantillas Excel han sido generadas correctamente.');

        return self::SUCCESS;
    }
}
