<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\System\Identity;

use App\Http\Controllers\Controller;
use App\Services\Identity\ExcelTemplateService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TemplateDownloadController extends Controller
{
    public function __construct(private readonly ExcelTemplateService $templates) {}

    /**
     * Descarga la plantilla Excel del tipo pedido, generándola en
     * storage/app/templates si todavía no existe en disco.
     */
    public function __invoke(Request $request, string $type): BinaryFileResponse
    {
        abort_unless($this->templates->supports($type), 404);

        $path = $this->templates->ensure($type);

        return response()->download($path, $this->filename($type), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Nombre de descarga versionado por fecha: plantilla_docentes_2026-09-29.xlsx
     */
    private function filename(string $type): string
    {
        return $this->templates->downloadName($type, now()->toDateString());
    }
}
