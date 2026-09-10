<?php

use App\Services\Incidents\IncidentPdfService;
use App\Services\Reports\CarnetPdfService;
use App\Services\Reports\GradebookPdfService;
use App\Services\Reports\PdfReportRenderer;

it('resuelve la carpeta de cada familia de reporte', function (): void {
    $renderer = new PdfReportRenderer(
        Mockery::mock(IncidentPdfService::class),
        Mockery::mock(CarnetPdfService::class),
        Mockery::mock(GradebookPdfService::class),
    );

    expect($renderer->subdirectory('incident_notification'))->toBe('incidents')
        ->and($renderer->subdirectory('incident_commitment_letter'))->toBe('incidents')
        ->and($renderer->subdirectory('incident_report'))->toBe('incidents')
        ->and($renderer->subdirectory('carnet_bulk'))->toBe('carnets')
        ->and($renderer->subdirectory('carnet_individual'))->toBe('carnets')
        ->and($renderer->subdirectory(GradebookPdfService::FORMATIVE))->toBe('gradebooks')
        ->and($renderer->subdirectory(GradebookPdfService::STUDENT_ANNUAL))->toBe('gradebooks');

    expect(fn () => $renderer->subdirectory('otro'))
        ->toThrow(RuntimeException::class, 'Tipo de reporte sin carpeta: otro');
});

it('delega el render y el nombre según el tipo al servicio correcto', function (): void {
    $incidents = Mockery::mock(IncidentPdfService::class);
    $carnets = Mockery::mock(CarnetPdfService::class);
    $gradebooks = Mockery::mock(GradebookPdfService::class);

    $renderer = new PdfReportRenderer($incidents, $carnets, $gradebooks);

    $incidents->shouldReceive('commitmentLetter')->once()->with(9)
        ->andReturn(['binary' => 'acta-bin', 'filename' => 'acta-C1.pdf']);
    $incidents->shouldReceive('filename')->once()->with('incident_commitment_letter', 9)->andReturn('acta-C1.pdf');

    $carnets->shouldReceive('bulk')->once()->with(['teacher_id' => 1])
        ->andReturn(['binary' => 'carnets-bin', 'filename' => 'carnets-8A-2026.pdf']);
    $carnets->shouldReceive('filename')->once()->with('carnet_bulk', ['teacher_id' => 1])->andReturn('carnets-8A-2026.pdf');

    $gradebooks->shouldReceive('studentAnnual')->once()->with(['teacher_id' => 1])
        ->andReturn(['binary' => 'anual-bin', 'filename' => 'Reporte_Anual_Juan_Perez.pdf']);
    $gradebooks->shouldReceive('filename')->once()->with(GradebookPdfService::STUDENT_ANNUAL, ['teacher_id' => 1])->andReturn('Reporte_Anual_Juan_Perez.pdf');

    expect($renderer->render('incident_commitment_letter', 9))->toBe(['binary' => 'acta-bin', 'filename' => 'acta-C1.pdf'])
        ->and($renderer->filename('incident_commitment_letter', 9))->toBe('acta-C1.pdf')
        ->and($renderer->render('carnet_bulk', null, ['teacher_id' => 1]))->toBe(['binary' => 'carnets-bin', 'filename' => 'carnets-8A-2026.pdf'])
        ->and($renderer->filename('carnet_bulk', null, ['teacher_id' => 1]))->toBe('carnets-8A-2026.pdf')
        ->and($renderer->render(GradebookPdfService::STUDENT_ANNUAL, null, ['teacher_id' => 1]))->toBe(['binary' => 'anual-bin', 'filename' => 'Reporte_Anual_Juan_Perez.pdf'])
        ->and($renderer->filename(GradebookPdfService::STUDENT_ANNUAL, null, ['teacher_id' => 1]))->toBe('Reporte_Anual_Juan_Perez.pdf');
});

it('lanza para un tipo de reporte desconocido', function (): void {
    $renderer = new PdfReportRenderer(
        Mockery::mock(IncidentPdfService::class),
        Mockery::mock(CarnetPdfService::class),
        Mockery::mock(GradebookPdfService::class),
    );

    expect(fn () => $renderer->render('desconocido', 1))
        ->toThrow(RuntimeException::class, 'Tipo de reporte no soportado: desconocido');
});
