<?php

use App\Jobs\SendChannelMessageJob;
use App\Jobs\SendReportNotification;
use App\Services\ReportStorageService;
use Illuminate\Support\Facades\Bus;

it('sin canal solo resuelve la URL del reporte y no encola envío', function (): void {
    Bus::fake();

    $storage = Mockery::mock(ReportStorageService::class);
    $storage->shouldReceive('url')->once()->with('reports/incidents/notificacion-1.pdf')->andReturn('https://x.test/reports/1');

    $job = new SendReportNotification('reports/incidents/notificacion-1.pdf', 'notificacion-1.pdf');
    $job->handle($storage);

    Bus::assertNotDispatched(SendChannelMessageJob::class);
});

it('con canal y destinatario encola el envío con la URL firmada', function (): void {
    Bus::fake();

    $storage = Mockery::mock(ReportStorageService::class);
    $storage->shouldReceive('url')->once()->andReturn('https://x.test/reports/1');

    $job = new SendReportNotification(
        'reports/incidents/notificacion-1.pdf',
        'notificacion-1.pdf',
        'email',
        'destino@test.com',
    );

    $job->handle($storage);

    Bus::assertDispatched(SendChannelMessageJob::class, fn (SendChannelMessageJob $job) => $job->channel === 'email'
        && $job->to === 'destino@test.com'
        && str_contains($job->message, 'https://x.test/reports/1'));
});
