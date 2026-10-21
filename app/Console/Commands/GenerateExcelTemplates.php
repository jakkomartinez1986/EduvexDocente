<?php

namespace App\Console\Commands;

use App\Services\Identity\ExcelTemplateService;
use Illuminate\Console\Command;

class GenerateExcelTemplates extends Command
{
    protected $signature = 'templates:generate';

    protected $description = 'Generar plantillas Excel para importacion de datos';

    public function handle(ExcelTemplateService $templates): int
    {
        foreach ($templates->generateAll() as $path) {
            $this->info("Plantilla generada: {$path}");
        }

        $this->info('Todas las plantillas Excel han sido generadas correctamente.');

        return self::SUCCESS;
    }
}
