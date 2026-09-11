<?php

use App\Models\Tenant;
use App\Models\User;

it('toont de features-overzicht pagina publiek per locale', function () {
    $this->get(route('product.features', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('product_docs.documents.features.label', [], 'nl'), false)
        ->assertSee('type="text/markdown"', false)
        ->assertSee(route('product.features.md', ['locale' => 'nl']), false);
});

it('toont de technische fiche publiek per locale', function () {
    $this->get(route('product.technical', ['locale' => 'en']))
        ->assertOk()
        ->assertSee(__('product_docs.documents.technical.label', [], 'en'), false)
        ->assertSee('Sign in with Microsoft', false);
});

it('toont de API & Webhooks fiche publiek per locale', function () {
    $this->get(route('product.api_webhooks', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('product_docs.documents.api_webhooks.label', [], 'nl'))
        ->assertSee(__('product_docs.api_webhooks.highlight', [], 'nl'));
});

it('redirect legacy /docs/features naar gelokaliseerde URL', function () {
    $this->get('/docs/features')
        ->assertRedirect();
});

it('redirect legacy /docs/api_webhooks naar gelokaliseerde URL', function () {
    $this->get('/docs/api_webhooks')
        ->assertRedirect();
});

it('serveert productfiches als Markdown', function () {
    $this->get(route('product.technical.md', ['locale' => 'en']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertSee('# Technical fact sheet', false)
        ->assertSee('Cloud86', false)
        ->assertSee('Sign in with Microsoft', false);

    $this->get(route('product.features.md', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee('# Features-overzicht', false);

    $this->withHeader('Accept', 'text/markdown')
        ->get(route('product.api_webhooks', ['locale' => 'en']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertSee('# API & Webhooks', false);
});

it('redirect /docs/technical.md naar gelokaliseerde Markdown-URL', function () {
    $this->get('/docs/technical.md')
        ->assertRedirect();
});

it('toont productfiches in de handleidingen-hub na login', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin()->for($tenant)->create();

    $this->actingAs($user)
        ->get(route('manual.hub'))
        ->assertOk()
        ->assertSee(__('manual.hub.features_overview'), false)
        ->assertSee(__('manual.hub.technical_sheet'), false)
        ->assertSee(__('manual.hub.api_webhooks'));
});
