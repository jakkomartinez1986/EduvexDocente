<?php

use App\Jobs\GeneratePdfReport;
use App\Jobs\SendReportNotification;
use App\Services\Academic\PdfReportCache;
use App\Services\Reports\GradebookPdfService;
use App\Services\Reports\PdfReportRenderer;
use App\Services\ReportStorageService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

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

it('versiona uniqueId con la firma de buckets del gradebook (capa 3)', function (): void {
    Cache::flush();
    $cache = app(PdfReportCache::class);
    $ctx = ['teacher_id' => 7, 'subject_id' => 3, 'grade_id' => 5, 'year_id' => 1, 'trimester_id' => 2];

    $before = (new GeneratePdfReport(GradebookPdfService::FORMATIVE, null, $ctx))->uniqueId();

    expect((new GeneratePdfReport(GradebookPdfService::FORMATIVE, null, $ctx))->uniqueId())
        ->toBe($before);

    $cache->invalidateForSubjectGrade(3, 5);

    expect((new GeneratePdfReport(GradebookPdfService::FORMATIVE, null, $ctx))->uniqueId())
        ->not->toBe($before);
});

it('no versiona uniqueId para tipos que no dependen de buckets del gradebook', function (): void {
    expect((new GeneratePdfReport('incident_notification', 42))->uniqueId())
        ->toBe('incident_notification:42');
});
