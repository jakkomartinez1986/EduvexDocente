<?php

use App\Jobs\GeneratePdfReport;
use App\Services\Reports\AsyncReportDispatcher;
use App\Services\Reports\PdfReportRenderer;
use App\Services\ReportStorageService;
use Illuminate\Support\Facades\Bus;

it('compone la ruta del reporte a partir del tipo y el nombre', function (): void {
    $renderer = Mockery::mock(PdfReportRenderer::class);
    $renderer->shouldReceive('subdirectory')->with('gradebook_formative')->andReturn('gradebooks');
    $renderer->shouldReceive('subdirectory')->with('carnet_individual')->andReturn('carnets');

    $dispatcher = new AsyncReportDispatcher($renderer, Mockery::mock(ReportStorageService::class));

    expect($dispatcher->path('gradebook_formative', 'Notas.pdf'))->toBe('reports/gradebooks/Notas.pdf')
        ->and($dispatcher->path('carnet_individual', 'carnet-COD.pdf'))->toBe('reports/carnets/carnet-COD.pdf');
});

it('delega ready y signedUrl al storage', function (): void {
    $renderer = Mockery::mock(PdfReportRenderer::class);

    $storage = Mockery::mock(ReportStorageService::class);
    $storage->shouldReceive('exists')->with('reports/gradebooks/Notas.pdf')->andReturn(true);
    $storage->shouldReceive('url')->with('reports/gradebooks/Notas.pdf')->andReturn('https://x.test/reports/gradebooks/Notas.pdf');

    $dispatcher = new AsyncReportDispatcher($renderer, $storage);

    expect($dispatcher->ready('reports/gradebooks/Notas.pdf'))->toBeTrue()
        ->and($dispatcher->signedUrl('reports/gradebooks/Notas.pdf'))->toBe('https://x.test/reports/gradebooks/Notas.pdf');
});

it('encola la generación del reporte con tipo, entidad y contexto', function (): void {
    Bus::fake();

    $dispatcher = new AsyncReportDispatcher(
        Mockery::mock(PdfReportRenderer::class),
        Mockery::mock(ReportStorageService::class),
    );

    $dispatcher->dispatch('gradebook_formative', null, ['teacher_id' => 7]);

    Bus::assertDispatched(GeneratePdfReport::class, fn (GeneratePdfReport $job) => $job->type === 'gradebook_formative'
        && $job->entityId === null
        && $job->context === ['teacher_id' => 7]);
});
