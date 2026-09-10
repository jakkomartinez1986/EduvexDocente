<?php

namespace App\Http\Controllers\Web\System\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\System\Teacher\Concerns\DispatchesAsyncPdfReports;
use App\Models\Incidents\NotificationChannel;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Services\Reports\PdfReportRenderer;

class IncidentPdfController extends Controller
{
    use DispatchesAsyncPdfReports;

    public function notification(int $id)
    {
        $notification = AcademicNotification::with(['student.user', 'teacher.user', 'grade', 'subject'])->findOrFail($id);

        // La marcación de impresión no se escribe síncronamente en el GET: se
        // difiere hasta después de responder (C-04) para no mutar la base en la
        // petición ni bloquear la descarga.
        defer(function () use ($notification): void {
            $notification->update(['printed_at' => now()]);
            NotificationChannel::where('notification_id', $notification->id)
                ->where('channel', 'impresa')
                ->update(['printed_at' => now()]);
        });

        return $this->asyncPdf(
            PdfReportRenderer::INCIDENT_NOTIFICATION,
            $id,
            [],
            'Notificación',
        );
    }

    public function commitmentLetter(int $id)
    {
        return $this->asyncPdf(
            PdfReportRenderer::INCIDENT_COMMITMENT_LETTER,
            $id,
            [],
            'Acta de compromiso',
        );
    }

    public function report(int $id)
    {
        return $this->asyncPdf(
            PdfReportRenderer::INCIDENT_REPORT,
            $id,
            [],
            'Informe',
        );
    }
}
