<?php

use App\Jobs\GeneratePdfReport;
use App\Jobs\SendReportNotification;
use App\Services\Incidents\IncidentPdfService;
use App\Services\ReportStorageService;
use Illuminate\Support\Facades\Bus;

it('genera, persiste el reporte y encola su notificación', function (): void {
    Bus::fake();

    $pdf = Mockery::mock();
    $pdf->shouldReceive('output')->once()->andReturn('pdf-contenido');

    $incidents = Mockery::mock(IncidentPdfService::class);
    $incidents->shouldReceive('notification')->once()->with(42)->andReturn($pdf);

    $storage = Mockery::mock(ReportStorageService::class);
    $storage->shouldReceive('store')
        ->once()
        ->with('pdf-contenido', 'incidents', 'incident-notification-42.pdf')
        ->andReturn('reports/incidents/incident-notification-42.pdf');

    $job = new GeneratePdfReport('incident_notification', 42);

    $job->handle($incidents, $storage);

    Bus::assertDispatched(SendReportNotification::class, fn (SendReportNotification $job) => $job->path === 'reports/incidents/incident-notification-42.pdf'
        && $job->filename === 'incident-notification-42.pdf');
});

it('lanza para un tipo de reporte no soportado', function (): void {
    $storage = Mockery::mock(ReportStorageService::class);
    $incidents = Mockery::mock(IncidentPdfService::class);

    $job = new GeneratePdfReport('desconocido', 1);

    expect(fn () => $job->handle($incidents, $storage))
        ->toThrow(RuntimeException::class, 'Tipo de reporte no soportado: desconocido');
});
