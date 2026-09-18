<?php

declare(strict_types=1);

it('toont publieke prijzenpagina met WinProx-jaarformules en Corporate voor gasten', function () {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee(__('subscription.plans.winprox_10.name'))
        ->assertSee(__('subscription.plans.winprox_10.price'))
        ->assertSee(__('subscription.plans.winprox_10.scale'))
        ->assertSee(__('subscription.plans.winprox_25.name'))
        ->assertSee(__('subscription.plans.winprox_25.price'))
        ->assertSee(__('subscription.plans.winprox_50.name'))
        ->assertSee(__('subscription.plans.winprox_50.price'))
        ->assertSee(__('subscription.plans.corporate.name'))
        ->assertSee(__('subscription.comparison_heading'))
        ->assertSee(__('subscription.yearly_invoice_notice'))
        ->assertSee(__('subscription.public_contact_cta'))
        ->assertSee(__('subscription.public_register_cta'), false)
        ->assertSee(__('subscription.glossary.seat'))
        ->assertSee(__('subscription.glossary.unit'))
        ->assertSee(__('subscription.comparison_col_seats'))
        ->assertSee(__('subscription.comparison_notes.trial'))
        ->assertSee(__('subscription.comparison_notes.unlimited'))
        ->assertSee(__('subscription.comparison_notes.corporate'))
        ->assertDontSee(__('subscription.plans.facility_25.price'))
        ->assertDontSee(__('subscription.time_addon.public_hint', ['price' => '€29']), false);
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
        ->assertSee(__('subscription.plans.winprox_25.name'))
        ->assertSee(__('subscription.plans.corporate.name'))
        ->assertSee(__('subscription.yearly_invoice_notice'))
        ->assertDontSee(__('subscription.plans.facility_25.price'));
});
