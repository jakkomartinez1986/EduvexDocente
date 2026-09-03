<?php

declare(strict_types=1);

use App\Services\Academic\PdfReportCache;

it('devuelve el valor generado y lo reutiliza en llamadas subsecuentes', function (): void {
    $cache = app(PdfReportCache::class);

    $calls = 0;
    $render = function () use (&$calls): string {
        $calls++;

        return 'pdf-bytes';
    };

    $first = $cache->remember('formative', ['year' => 1], ['teacher:1'], $render);
    $second = $cache->remember('formative', ['year' => 1], ['teacher:1'], $render);

    expect($first)->toBe('pdf-bytes')
        ->and($second)->toBe('pdf-bytes')
        ->and($calls)->toBe(1);
});

it('genera una clave distinta para parámetros o tipo distintos', function (): void {
    $cache = app(PdfReportCache::class);

    $k1 = $cache->key('formative', ['year' => 1, 'student' => 2], ['teacher:1']);
    $k2 = $cache->key('formative', ['year' => 1, 'student' => 3], ['teacher:1']);
    $k3 = $cache->key('summative', ['year' => 1, 'student' => 2], ['teacher:1']);

    expect($k1)->not->toBe($k2)
        ->and($k1)->not->toBe($k3);
});

it('invalida forzando regeneración al cambiar la versión del bucket', function (): void {
    $cache = app(PdfReportCache::class);

    $calls = 0;
    $render = function () use (&$calls): string {
        $calls++;

        return 'pdf-bytes';
    };

    $cache->remember('formative', ['year' => 1], ['teacher:1'], $render);
    $cache->invalidateForTeacher(1);
    $cache->remember('formative', ['year' => 1], ['teacher:1'], $render);

    expect($calls)->toBe(2);
});

it('no invalida claves de buckets no afectados', function (): void {
    $cache = app(PdfReportCache::class);

    $callsTen = 0;
    $renderTen = function () use (&$callsTen): string {
        $callsTen++;

        return 'pdf-a';
    };

    $callsTwenty = 0;
    $renderTwenty = function () use (&$callsTwenty): string {
        $callsTwenty++;

        return 'pdf-b';
    };

    $cache->remember('student-annual', ['year' => 1, 'student' => 10], ['teacher:5', 'student:10'], $renderTen);
    $cache->remember('student-annual', ['year' => 1, 'student' => 20], ['teacher:5', 'student:20'], $renderTwenty);

    $cache->invalidateForStudent(10);

    $cache->remember('student-annual', ['year' => 1, 'student' => 20], ['teacher:5', 'student:20'], $renderTwenty);

    expect($callsTen)->toBe(1)
        ->and($callsTwenty)->toBe(1);
});
