<?php

namespace App\Services\Reports;

use App\Services\Incidents\IncidentPdfService;

/**
 * Registro de renderers de reportes PDF (C-04). Centraliza el mapa tipo→carpeta,
 * tipo→nombre de archivo y tipo→render para que los controllers async y el job
 * GeneratePdfReport compartan un único punto de verdad sobre cómo se produce
 * cada reporte y dónde vive en el Object Storage.
 */
class PdfReportRenderer
{
    public const INCIDENT_NOTIFICATION = 'incident_notification';

    public const INCIDENT_COMMITMENT_LETTER = 'incident_commitment_letter';

    public const INCIDENT_REPORT = 'incident_report';

    public const CARNET_BULK = 'carnet_bulk';

    public const CARNET_INDIVIDUAL = 'carnet_individual';

    public function __construct(
        private readonly IncidentPdfService $incidents,
        private readonly CarnetPdfService $carnets,
        private readonly GradebookPdfService $gradebooks,
    ) {}

    public const GRADEBOOK_TYPES = [
        GradebookPdfService::FORMATIVE,
        GradebookPdfService::SUMMATIVE,
        GradebookPdfService::QUALITATIVE,
        GradebookPdfService::SUBJECT_ANNUAL,
        GradebookPdfService::SUPLETORIO,
        GradebookPdfService::TUTOR_STUDENT,
        GradebookPdfService::TUTOR_STUDENT_TRI,
        GradebookPdfService::TUTOR_FORMATIVE_TRI,
        GradebookPdfService::TUTOR_ALL_TRI,
        GradebookPdfService::STUDENT_TRI,
        GradebookPdfService::STUDENT_ANNUAL,
    ];

    /**
     * Subdirectorio bajo reports/ donde vive cada tipo de reporte.
     */
    public function subdirectory(string $type): string
    {
        return match (true) {
            $type === self::INCIDENT_NOTIFICATION || $type === self::INCIDENT_COMMITMENT_LETTER || $type === self::INCIDENT_REPORT => 'incidents',
            $type === self::CARNET_BULK || $type === self::CARNET_INDIVIDUAL => 'carnets',
            in_array($type, self::GRADEBOOK_TYPES, true) => 'gradebooks',
            default => throw new \RuntimeException("Tipo de reporte sin carpeta: {$type}"),
        };
    }

    /**
     * Nombre determinístico del archivo (usado para el chequeo previo al
     * dispatch y como nombre de presentación).
     *
     * @param  array<string, mixed>  $context
     */
    public function filename(string $type, ?int $entityId, array $context = []): string
    {
        return match ($type) {
            self::INCIDENT_NOTIFICATION, self::INCIDENT_COMMITMENT_LETTER, self::INCIDENT_REPORT => $this->incidents->filename($type, $entityId),
            self::CARNET_BULK, self::CARNET_INDIVIDUAL => $this->carnets->filename($type, $context),
            default => $this->gradebooks->filename($type, $context),
        };
    }

    /**
     * Render completo del reporte.
     *
     * @param  array<string, mixed>  $context
     * @return array{binary: string, filename: string}
     */
    public function render(string $type, ?int $entityId, array $context = []): array
    {
        return match ($type) {
            self::INCIDENT_NOTIFICATION => $this->incidents->notification($entityId),
            self::INCIDENT_COMMITMENT_LETTER => $this->incidents->commitmentLetter($entityId),
            self::INCIDENT_REPORT => $this->incidents->report($entityId),
            self::CARNET_BULK => $this->carnets->bulk($context),
            self::CARNET_INDIVIDUAL => $this->carnets->individual($entityId, $context),
            GradebookPdfService::FORMATIVE => $this->gradebooks->formative($context),
            GradebookPdfService::SUMMATIVE => $this->gradebooks->summative($context),
            GradebookPdfService::QUALITATIVE => $this->gradebooks->qualitative($context),
            GradebookPdfService::SUBJECT_ANNUAL => $this->gradebooks->subjectAnnual($context),
            GradebookPdfService::SUPLETORIO => $this->gradebooks->supletorio($context),
            GradebookPdfService::TUTOR_STUDENT => $this->gradebooks->tutorStudent($context),
            GradebookPdfService::TUTOR_STUDENT_TRI => $this->gradebooks->tutorStudentTri($context),
            GradebookPdfService::TUTOR_FORMATIVE_TRI => $this->gradebooks->tutorFormativeTri($context),
            GradebookPdfService::TUTOR_ALL_TRI => $this->gradebooks->tutorAllTri($context),
            GradebookPdfService::STUDENT_TRI => $this->gradebooks->studentTri($context),
            GradebookPdfService::STUDENT_ANNUAL => $this->gradebooks->studentAnnual($context),
            default => throw new \RuntimeException("Tipo de reporte no soportado: {$type}"),
        };
    }
}
