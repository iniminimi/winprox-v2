<?php

declare(strict_types=1);

it('toont tijdelijk onbeschikbare prijzenpagina voor gasten', function () {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee(__('pricing.title'))
        ->assertSee(__('pricing.message'))
        ->assertSee(__('pricing.trial_link'), false)
        ->assertDontSee(__('subscription.choose_plan'), false);
});

it('toont tijdelijk onbeschikbare prijzenpagina voor ingelogde gebruikers', function () {
    $tenant = \App\Models\Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
    $user = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('pricing'))
        ->assertOk()
        ->assertSee(__('pricing.title'))
        ->assertSee(__('pricing.subscription_link'), false)
        ->assertDontSee(__('subscription.choose_plan'), false);
});

it('toont plan-knoppen op abonnementenpagina voor beheerder', function () {
    $tenant = \App\Models\Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
    $admin = \App\Models\User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire\Livewire::actingAs($admin)
        ->test(\App\Livewire\Pages\Subscription::class)
        ->assertSee(__('subscription.choose_plan'), false);
});
