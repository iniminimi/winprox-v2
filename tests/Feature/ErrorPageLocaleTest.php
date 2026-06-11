<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_test-error-locale-404', fn () => abort(404));
});

it('renders 404 in Dutch by default', function () {
    $response = $this->get('/wp-nonexistent-route-for-locale-test');

    $response->assertNotFound();
    $response->assertSee('Pagina niet gevonden', false);
    $response->assertDontSee('Page not found', false);
});

it('renders 404 in English when user locale is en', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)->get('/_test-error-locale-404');

    $response->assertNotFound();
    $response->assertSee('Page not found', false);
    $response->assertDontSee('Pagina niet gevonden', false);
});
