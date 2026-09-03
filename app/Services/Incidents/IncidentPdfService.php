<?php

namespace App\Services\Incidents;

use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Services\SchoolConfigService;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;

/**
 * Render de los PDFs del libro de incidencias. Centraliza la construcción del
 * documento (vista + data + opciones de DomPDF) para que el controller síncrono
 * y el job asíncrono GeneratePdfReport compartan la misma lógica y no dupliquen
 * queries ni opciones.
 */
class IncidentPdfService
{
    public function __construct(private readonly SchoolConfigService $schoolConfig) {}

    /**
     * PDF de notificación (impresa/citación) lista para descargar o serializar.
     */
    public function notification(int $id)
    {
        $notification = AcademicNotification::with([
            'student.user', 'teacher.user', 'grade', 'subject', 'channels',
        ])->findOrFail($id);

        $pdf = PdfFacade::loadView('pdf.incidents.notification', [
            'notification' => $notification,
            'school' => $this->schoolConfig->getActiveSchool(),
            'channels' => $notification->channels ?? collect(),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);

        return $pdf;
    }
}
