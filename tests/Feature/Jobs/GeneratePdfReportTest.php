<?php

use App\Jobs\GeneratePdfReport;
use App\Jobs\SendReportNotification;
use App\Services\Reports\PdfReportRenderer;
use App\Services\ReportStorageService;
use Illuminate\Support\Facades\Bus;

it('genera, persiste el reporte y encola su notificación con la URL firmada', function (): void {
    Bus::fake();

    $renderer = Mockery::mock(PdfReportRenderer::class);
    $renderer->shouldReceive('subdirectory')->once()->with('incident_notification')->andReturn('incidents');
    $renderer->shouldReceive('render')->once()->with('incident_notification', 42, [])->andReturn([
        'binary' => 'pdf-contenido',
        'filename' => 'notificacion-NOT-100.pdf',
    ]);

    $storage = Mockery::mock(ReportStorageService::class);
    $storage->shouldReceive('store')
        ->once()
        ->with('pdf-contenido', 'incidents', 'notificacion-NOT-100.pdf')
        ->andReturn('reports/incidents/notificacion-NOT-100.pdf');

    $job = new GeneratePdfReport('incident_notification', 42);

    $job->handle($renderer, $storage);

    Bus::assertDispatched(SendReportNotification::class, fn (SendReportNotification $job) => $job->path === 'reports/incidents/notificacion-NOT-100.pdf'
        && $job->filename === 'notificacion-NOT-100.pdf');
});

it('propaga el contexto serializable y el canal opcional a la notificación', function (): void {
    Bus::fake();

    $renderer = Mockery::mock(PdfReportRenderer::class);
    $renderer->shouldReceive('subdirectory')->once()->with('gradebook_formative')->andReturn('gradebooks');
    $renderer->shouldReceive('render')->once()->with('gradebook_formative', null, [
        'teacher_id' => 7,
        'teacher_name' => 'Docente',
        'subject_id' => 3,
    ])->andReturn([
        'binary' => 'pdf-x',
        'filename' => 'Notas_Formativas_8A_Q1.pdf',
    ]);

    $storage = Mockery::mock(ReportStorageService::class);
    $storage->shouldReceive('store')
        ->once()
        ->with('pdf-x', 'gradebooks', 'Notas_Formativas_8A_Q1.pdf')
        ->andReturn('reports/gradebooks/Notas_Formativas_8A_Q1.pdf');

    $job = new GeneratePdfReport(
        'gradebook_formative',
        null,
        ['teacher_id' => 7, 'teacher_name' => 'Docente', 'subject_id' => 3],
        'whatsapp',
        '+593999999999',
    );

    $job->handle($renderer, $storage);

    Bus::assertDispatched(SendReportNotification::class, fn (SendReportNotification $job) => $job->channel === 'whatsapp'
        && $job->to === '+593999999999');
});

it('lanza para un tipo de reporte no soportado', function (): void {
    $renderer = Mockery::mock(PdfReportRenderer::class);
    $renderer->shouldReceive('render')->once()->with('desconocido', 1, [])
        ->andThrow(RuntimeException::class, 'Tipo de reporte no soportado: desconocido');

    $storage = Mockery::mock(ReportStorageService::class);

    $job = new GeneratePdfReport('desconocido', 1);

    expect(fn () => $job->handle($renderer, $storage))
        ->toThrow(RuntimeException::class, 'Tipo de reporte no soportado: desconocido');
});
