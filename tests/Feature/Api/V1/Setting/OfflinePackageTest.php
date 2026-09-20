<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

it('responde el paquete offline con catálogos estáticos cacheados', function (): void {
    $context = academicContext();

    /** @var User $user */
    $user = $context['teacher']->user;

    $response = $this->getJson('/api/v1/settings/bootstrap', bearerTokenFor($user));

    $response->assertOk()
        ->assertJsonPath('success', true);

    $data = $response->json('data');

    expect($data['catalogs']['shifts'])->toBeArray()
        ->and($data['catalogs']['nivels'])->toBeArray()
        ->and($data['catalogs']['grades'])->toBeArray()
        ->and($data['catalogs']['areas'])->toBeArray()
        ->and($data['catalogs']['subjects'])->toBeArray()
        ->and($data['catalogs']['classrooms'])->toBeArray();

    expect(Cache::has('eduvex:'.app()->environment().':catalog:static'))->toBeTrue();
});

it('es stable entre peticiones (mismo catálogo cacheado)', function (): void {
    $context = academicContext();
    $user = $context['teacher']->user;

    $first = $this->getJson('/api/v1/settings/bootstrap', bearerTokenFor($user))->json('data.catalogs');
    $second = $this->getJson('/api/v1/settings/bootstrap', bearerTokenFor($user))->json('data.catalogs');

    expect($second)->toBe($first);
});

it('embebe los logos de la escuela en el paquete offline', function (): void {
    $context = academicContext();
    $user = $context['teacher']->user;

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

    Storage::disk('public')->put('uploads/logo-test.png', $png);
    Storage::disk('public')->put('uploads/report-test.png', $png);

    $context['school']->update([
        'logo_path' => 'uploads/logo-test.png',
        'report_logo_path' => 'uploads/report-test.png',
    ]);

    $data = $this->getJson('/api/v1/settings/bootstrap', bearerTokenFor($user))
        ->assertOk()
        ->json('data');

    expect($data['logos'])->not->toBeNull()
        ->and($data['logos']['logo'])->toStartWith('data:image/')
        ->and($data['logos']['report_logo'])->toStartWith('data:image/')
        ->and(str_contains((string) $data['logos']['logo'], base64_encode($png)))->toBeTrue();
});

it('devuelve logos null cuando la escuela no los configuró', function (): void {
    $context = academicContext();

    $data = $this->getJson('/api/v1/settings/bootstrap', bearerTokenFor($context['teacher']->user))
        ->assertOk()
        ->json('data');

    expect($data['logos']['logo'])->toBeNull()
        ->and($data['logos']['report_logo'])->toBeNull();
});
