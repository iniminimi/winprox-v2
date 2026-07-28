<?php

declare(strict_types=1);

use App\Actions\Marketing\SubmitIndexNowUrlsAction;
use App\Support\Marketing\MarketingSeo;
use Illuminate\Support\Facades\Http;

it('serveert het IndexNow key-bestand uit de public root', function () {
    $key = (string) config('indexnow.key');

    expect(is_file(public_path($key.'.txt')))->toBeTrue()
        ->and(trim((string) file_get_contents(public_path($key.'.txt'))))->toBe($key);

    $this->get('/'.$key.'.txt')
        ->assertOk()
        ->assertSee($key, false);
});

it('stuurt marketing-URL\'s naar IndexNow', function () {
    Http::fake([
        'api.indexnow.org/*' => Http::response('', 200),
    ]);

    config([
        'indexnow.enabled' => true,
        'indexnow.key' => '2c081d71a6a943a19a81fbb727f93cf4',
        'indexnow.host' => 'winprox.app',
        'app.url' => 'https://winprox.app',
    ]);

    $result = app(SubmitIndexNowUrlsAction::class)->handle();

    expect($result['submitted'])->toBe(count(MarketingSeo::sitemapUrls()))
        ->and($result['host'])->toBe('winprox.app')
        ->and($result['key_location'])->toBe('https://winprox.app/2c081d71a6a943a19a81fbb727f93cf4.txt')
        ->and($result['status'])->toBe(200)
        ->and($result['error'])->toBeNull();

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://api.indexnow.org/indexnow'
            && ($data['host'] ?? null) === 'winprox.app'
            && ($data['key'] ?? null) === '2c081d71a6a943a19a81fbb727f93cf4'
            && ($data['keyLocation'] ?? null) === 'https://winprox.app/2c081d71a6a943a19a81fbb727f93cf4.txt'
            && is_array($data['urlList'] ?? null)
            && count($data['urlList']) > 0;
    });
});

it('accepteert IndexNow HTTP 202 als succes', function () {
    Http::fake([
        'api.indexnow.org/*' => Http::response('', 202),
    ]);

    config([
        'indexnow.enabled' => true,
        'indexnow.key' => '2c081d71a6a943a19a81fbb727f93cf4',
        'indexnow.host' => 'winprox.app',
    ]);

    $result = app(SubmitIndexNowUrlsAction::class)->handle([
        'https://winprox.app/nl',
    ]);

    expect($result['status'])->toBe(202)
        ->and($result['submitted'])->toBe(1)
        ->and($result['error'])->toBeNull();
});

it('dry-run roept IndexNow niet aan', function () {
    Http::fake();

    config([
        'indexnow.enabled' => true,
        'indexnow.key' => '2c081d71a6a943a19a81fbb727f93cf4',
        'indexnow.host' => 'winprox.app',
    ]);

    $result = app(SubmitIndexNowUrlsAction::class)->handle(dryRun: true);

    expect($result['dry_run'])->toBeTrue()
        ->and($result['submitted'])->toBeGreaterThan(0);

    Http::assertNothingSent();
});

it('weigert IndexNow wanneer uitgeschakeld', function () {
    config(['indexnow.enabled' => false]);

    expect(fn () => app(SubmitIndexNowUrlsAction::class)->handle())
        ->toThrow(RuntimeException::class, 'IndexNow is disabled');
});
