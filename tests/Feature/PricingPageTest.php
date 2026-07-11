<?php

declare(strict_types=1);

use App\Livewire\Pages\Pricing;

it('toont publieke prijzenpagina zonder plan-knoppen', function () {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee(__('subscription.public_title'))
        ->assertSee(__('welcome.nav.products'), false)
        ->assertSee(__('welcome.nav.pricing'), false)
        ->assertSee(__('subscription.facility_heading'))
        ->assertSee(__('subscription.products.time.name'))
        ->assertSee(__('subscription.modules.esg.name'))
        ->assertSee(__('subscription.modules.time.name'))
        ->assertDontSee(__('subscription.choose_plan'), false);
});

it('toont prijzenpagina voor ingelogde gebruikers zonder plan-knoppen', function () {
    $tenant = \App\Models\Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
    $user = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('pricing'))
        ->assertOk()
        ->assertSee(__('subscription.public_title'))
        ->assertDontSee(__('subscription.choose_plan'), false);
});

it('toont plan-knoppen op abonnementenpagina voor beheerder', function () {
    $tenant = \App\Models\Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
    $admin = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire\Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Subscription::class)
        ->assertSee(__('subscription.choose_plan'), false);
});
