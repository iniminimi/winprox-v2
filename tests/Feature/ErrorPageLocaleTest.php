<?php

use App\Models\User;
use App\Support\ResolveAppLocale;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_test-error-locale-404', fn () => abort(404));
});

it('renders 404 in Dutch by default', function () {
    $response = $this->withHeader('Accept-Language', 'xx-XX,xx;q=0.9')
        ->get('/wp-nonexistent-route-for-locale-test');

    $response->assertNotFound();
    $response->assertSee('Pagina niet gevonden', false);
    $response->assertDontSee('Page not found', false);
    $response->assertSee('Powered by Winprox.app', false);
});

it('renders 404 in English when user locale is en', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)->get('/_test-error-locale-404');

    $response->assertNotFound();
    $response->assertSee('Page not found', false);
    $response->assertDontSee('Pagina niet gevonden', false);
});

it('renders 404 in English when locale cookie is en', function () {
    Route::get('/_test-error-locale-cookie-404', fn () => abort(404));

    $response = $this->withUnencryptedCookie(ResolveAppLocale::COOKIE_NAME, 'en')
        ->get('/_test-error-locale-cookie-404');

    $response->assertNotFound();
    $response->assertSee('Page not found', false);
    $response->assertDontSee('Pagina niet gevonden', false);
});
