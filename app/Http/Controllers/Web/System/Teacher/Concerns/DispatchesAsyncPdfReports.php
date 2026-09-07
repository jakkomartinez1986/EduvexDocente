<?php

namespace App\Http\Controllers\Web\System\Teacher\Concerns;

use App\Services\Reports\AsyncReportDispatcher;
use App\Services\Reports\PdfReportRenderer;

/**
 * Flujo async de reportes PDF (C-04) compartido por los controladores que antes
 * descargaban el PDF síncronamente: si el reporte ya está persistido redirige a
 * su URL firmada; si no, encola la generación y muestra una página de espera
 * que refresca la misma URL hasta que el archivo exista.
 */
trait DispatchesAsyncPdfReports
{
    protected function asyncPdf(
        string $type,
        ?int $entityId,
        array $context = [],
        string $title = 'Documento',
    ): mixed {
        $renderer = app(PdfReportRenderer::class);
        $dispatcher = app(AsyncReportDispatcher::class);

        $filename = $renderer->filename($type, $entityId, $context);
        $path = $dispatcher->path($type, $filename);

        if ($dispatcher->ready($path)) {
            return redirect()->away($dispatcher->signedUrl($path));
        }

        $dispatcher->dispatch($type, $entityId, $context);

        return view('reports.processing', [
            'reportUrl' => request()->fullUrl(),
            'title' => $title,
            'filename' => $filename,
        ]);
    }
}
