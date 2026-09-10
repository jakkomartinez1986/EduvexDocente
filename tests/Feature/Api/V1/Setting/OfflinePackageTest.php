<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;

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
