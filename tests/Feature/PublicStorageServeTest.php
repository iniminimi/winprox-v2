<?php

use Illuminate\Support\Facades\Storage;

it('serves public disk files at /storage without a signed URL', function () {
    Storage::disk('public')->put('tenant-logos/serve-test.png', 'fake-png-bytes');

    $this->get('/storage/tenant-logos/serve-test.png')
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('generates relative public disk URLs independent of APP_URL', function () {
    config(['app.url' => 'http://wrong-host.example']);

    expect(Storage::disk('public')->url('tenant-logos/acme.png'))
        ->toBe('/storage/tenant-logos/acme.png');
});

it('does not let the private local disk claim /storage', function () {
    $routes = collect(app('router')->getRoutes())
        ->filter(fn ($route) => $route->getName() === 'storage.local')
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    expect($routes)->not->toContain('storage/{path}')
        ->and($routes)->toContain('local-files/{path}');
});
