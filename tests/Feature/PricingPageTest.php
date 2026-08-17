<?php

declare(strict_types=1);

it('toont publieke prijzenpagina met WinProx-jaarformules en Corporate voor gasten', function () {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee(__('subscription.plans.winprox_10.name'))
        ->assertSee(__('subscription.plans.winprox_50.name'))
        ->assertSee(__('subscription.plans.winprox_100.name'))
        ->assertSee(__('subscription.plans.corporate.name'))
        ->assertSee(__('subscription.comparison_heading'))
        ->assertSee(__('subscription.time_addon.public_hint', ['price' => '€29']), false)
        ->assertSee(__('subscription.public_contact_cta'))
        ->assertSee(__('subscription.public_register_cta'), false)
        ->assertDontSee(__('subscription.plans.facility_25.name'));
});

it('toont publieke prijzenpagina voor ingelogde gebruikers', function () {
    $tenant = \App\Models\Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
    $user = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('pricing'))
        ->assertOk()
        ->assertSee(__('subscription.plans.winprox_10.name'))
        ->assertSee(__('subscription.public_register_cta'), false);
});

it('toont plan-knoppen op abonnementenpagina voor beheerder', function () {
    $tenant = \App\Models\Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
    $admin = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire\Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Subscription::class)
        ->assertSee(__('subscription.choose_plan'), false)
        ->assertSee(__('subscription.plans.winprox_10.name'))
        ->assertSee(__('subscription.plans.winprox_100.name'))
        ->assertSee(__('subscription.plans.corporate.name'))
        ->assertSee(__('subscription.time_addon.label'));
});
