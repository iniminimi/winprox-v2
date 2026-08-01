<?php

use App\Models\Tenant;
use App\Models\User;

it('toont de features-overzicht pagina publiek per locale', function () {
    $this->get(route('product.features', ['locale' => 'nl']))
        ->assertOk()
        ->assertSee(__('product_docs.documents.features.label', [], 'nl'), false);
});

it('toont de technische fiche publiek per locale', function () {
    $this->get(route('product.technical', ['locale' => 'en']))
        ->assertOk()
        ->assertSee(__('product_docs.documents.technical.label', [], 'en'), false);
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
