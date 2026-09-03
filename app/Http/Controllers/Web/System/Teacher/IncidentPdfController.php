<?php

namespace App\Http\Controllers\Web\System\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Incidents\IncidentCommitmentLetter;
use App\Models\Incidents\IncidentReport;
use App\Models\Incidents\NotificationChannel;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Services\Incidents\IncidentPdfService;
use App\Services\SchoolConfigService;
use Barryvdh\DomPDF\Facade\Pdf;

class IncidentPdfController extends Controller
{
    public function __construct(
        private readonly IncidentPdfService $incidentPdfService,
        private readonly SchoolConfigService $schoolConfig,
    ) {}

    public function notification(int $id)
    {
        $notification = AcademicNotification::with(['student.user', 'teacher.user', 'grade', 'subject'])->findOrFail($id);

        // La marcación de impresión no se escribe síncronamente en el GET
        // (anti-patrón C-04): se difiere hasta después de responder para no
        // bloquear la descarga ni mutar la base dentro de una petición GET.
        defer(function () use ($notification): void {
            $notification->update(['printed_at' => now()]);
            NotificationChannel::where('notification_id', $notification->id)
                ->where('channel', 'impresa')
                ->update(['printed_at' => now()]);
        });

        $pdf = $this->incidentPdfService->notification($id);

        return $pdf->download("notificacion-{$notification->code}.pdf");
    }

    public function commitmentLetter(int $id)
    {
        $letter = IncidentCommitmentLetter::with([
            'student.user', 'teacher.user', 'grade', 'subject', 'representative.user',
        ])->findOrFail($id);

        $school = $this->schoolConfig->getActiveSchool();

        $pdf = Pdf::loadView('pdf.incidents.commitment-letter', [
            'letter' => $letter,
            'school' => $school,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);

        return $pdf->download("acta-{$letter->code}.pdf");
    }

    public function report(int $id)
    {
        $report = IncidentReport::with([
            'student.user', 'teacher.user', 'grade', 'subject', 'tutor.user',
        ])->findOrFail($id);

        $school = $this->schoolConfig->getActiveSchool();

        $notifications = AcademicNotification::where('student_id', $report->student_id)
            ->where('type', $report->type)
            ->with(['channels'])
            ->get();

        $letters = IncidentCommitmentLetter::where('student_id', $report->student_id)
            ->where('type', $report->type)
            ->get();

        $pdf = Pdf::loadView('pdf.incidents.report', [
            'report' => $report,
            'school' => $school,
            'notifications' => $notifications,
            'letters' => $letters,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);

        return $pdf->download("informe-{$report->code}.pdf");
    }
}
