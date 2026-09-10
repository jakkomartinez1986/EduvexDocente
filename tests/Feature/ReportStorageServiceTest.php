<?php

use App\Services\ReportStorageService;
use Illuminate\Support\Facades\Storage;

it('guarda el reporte en el subdirectorio del tipo sobre el disco configurado', function (): void {
    Storage::fake('local');

    $path = app(ReportStorageService::class)->store('pdf-contenido', 'incidents', 'notificacion-1.pdf');

    expect($path)->toBe('reports/incidents/notificacion-1.pdf')
        ->and(Storage::disk('local')->exists('reports/incidents/notificacion-1.pdf'))->toBeTrue()
        ->and(Storage::disk('local')->get('reports/incidents/notificacion-1.pdf'))->toBe('pdf-contenido');
});

it('resuelve la URL servida para un disco local/publico', function (): void {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');
    Storage::disk('public')->put('reports/incidents/notificacion-1.pdf', 'bytes');

    $url = app(ReportStorageService::class)->url('reports/incidents/notificacion-1.pdf');

    expect($url)->toBeString()
        ->and($url)->toContain('/storage/reports/incidents/notificacion-1.pdf');
});

it('resuelve una URL firmada para el disco local con serve habilitado', function (): void {
    config(['filesystems.default' => 'local']);

    $url = app(ReportStorageService::class)->url('reports/gradebooks/notas.pdf');

    expect($url)->toBeString()
        ->and($url)->toContain('/storage/reports/gradebooks/notas.pdf')
        ->and($url)->toContain('signature=')
        ->and($url)->toContain('expires=');
});
