<?php

use App\Support\ResolveAppLocale;
use Illuminate\Http\Request;

it('prefers session locale over cookie and default', function () {
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('locale', 'fr');
    $request->cookies->set(ResolveAppLocale::COOKIE_NAME, 'en');

    expect(ResolveAppLocale::resolve($request))->toBe('fr');
});

it('falls back to locale cookie when session is empty', function () {
    $request = Request::create('/');
    $request->cookies->set(ResolveAppLocale::COOKIE_NAME, 'de');

    expect(ResolveAppLocale::resolve($request))->toBe('de');
});

it('falls back to configured default for unsupported cookie values', function () {
    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_ACCEPT_LANGUAGE' => 'xx-XX,xx;q=0.9',
    ]);
    $request->cookies->set(ResolveAppLocale::COOKIE_NAME, 'xx');

    expect(ResolveAppLocale::resolve($request))->toBe(config('locales.default'));
});
