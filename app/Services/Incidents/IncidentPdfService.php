<?php

namespace App\Services\Incidents;

use App\Models\Incidents\IncidentCommitmentLetter;
use App\Models\Incidents\IncidentReport;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Services\SchoolConfigService;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;

/**
 * Render de los PDFs del libro de incidencias. Cada método devuelve
 * ['binary' => string, 'filename' => string] para que el job asíncrono
 * GeneratePdfReport persista el reporte sin acoplar el render al request.
 * filename() expone solo el nombre determinístico (misma nomenclatura) para el
 * chequeo de disponibilidad previo al dispatch.
 */
class IncidentPdfService
{
    public function __construct(private readonly SchoolConfigService $schoolConfig) {}

    /**
     * @return array{binary: string, filename: string}
     */
    public function notification(int $id): array
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

        return ['binary' => $pdf->output(), 'filename' => "notificacion-{$notification->code}.pdf"];
    }

    /**
     * @return array{binary: string, filename: string}
     */
    public function commitmentLetter(int $id): array
    {
        $letter = IncidentCommitmentLetter::with([
            'student.user', 'teacher.user', 'grade', 'subject', 'representative.user',
        ])->findOrFail($id);

        $pdf = PdfFacade::loadView('pdf.incidents.commitment-letter', [
            'letter' => $letter,
            'school' => $this->schoolConfig->getActiveSchool(),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);

        return ['binary' => $pdf->output(), 'filename' => "acta-{$letter->code}.pdf"];
    }

    /**
     * @return array{binary: string, filename: string}
     */
    public function report(int $id): array
    {
        $report = IncidentReport::with([
            'student.user', 'teacher.user', 'grade', 'subject', 'tutor.user',
        ])->findOrFail($id);

        $notifications = AcademicNotification::where('student_id', $report->student_id)
            ->where('type', $report->type)
            ->with(['channels'])
            ->get();

        $letters = IncidentCommitmentLetter::where('student_id', $report->student_id)
            ->where('type', $report->type)
            ->get();

        $pdf = PdfFacade::loadView('pdf.incidents.report', [
            'report' => $report,
            'school' => $this->schoolConfig->getActiveSchool(),
            'notifications' => $notifications,
            'letters' => $letters,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);

        return ['binary' => $pdf->output(), 'filename' => "informe-{$report->code}.pdf"];
    }

    /**
     * Nombre determinístico del archivo para un tipo de reporte de incidencias.
     */
    public function filename(string $type, int $id): string
    {
        return match ($type) {
            'incident_notification' => 'notificacion-'.AcademicNotification::findOrFail($id)->code.'.pdf',
            'incident_commitment_letter' => 'acta-'.IncidentCommitmentLetter::findOrFail($id)->code.'.pdf',
            'incident_report' => 'informe-'.IncidentReport::findOrFail($id)->code.'.pdf',
            default => throw new \RuntimeException("Tipo de reporte no soportado: {$type}"),
        };
    }
}
