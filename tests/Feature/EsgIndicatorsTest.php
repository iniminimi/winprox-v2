<?php

use App\Livewire\Esg\IndicatorsIndex;
use App\Models\EsgIndicator;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

it('weigert toegang tot indicatoren zonder esg-module', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => false]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('esg.indicators.index'))
        ->assertForbidden();
});

it('weigert indicatoren voor medewerkers zonder admin-rechten', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->employee()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('esg.indicators.index'))
        ->assertForbidden();
});

it('laat een admin met esg-module indicatoren beheren', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->call('openCreateModal')
        ->set('name', 'Elektriciteit kWh')
        ->set('type', 'numeric')
        ->set('unitOfMeasure', 'kWh')
        ->set('thresholdMin', '0')
        ->set('thresholdMax', '99999')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Elektriciteit kWh');

    $indicator = EsgIndicator::query()->where('tenant_id', $tenant->id)->first();

    expect($indicator)->not->toBeNull()
        ->and($indicator->name)->toBe('Elektriciteit kWh')
        ->and($indicator->type->value)->toBe('numeric')
        ->and($indicator->unit_of_measure)->toBe('kWh')
        ->and($indicator->thresholds)->toMatchArray(['min' => 0, 'max' => 99999]);
});

it('deactiveert een indicator zonder hard delete', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $indicator = EsgIndicator::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Gas m3',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->call('toggleActive', $indicator->id)
        ->assertSee(__('esg.status.inactive'));

    expect($indicator->fresh()->is_active)->toBeFalse()
        ->and(EsgIndicator::query()->whereKey($indicator->id)->exists())->toBeTrue();
});

it('isoleert indicatoren per tenant', function () {
    $tenantA = Tenant::factory()->create(['has_esg_module' => true]);
    $tenantB = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);

    EsgIndicator::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Andere tenant']);

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->assertDontSee('Andere tenant');
});

it('toont setup-stappen bij lege indicatorenlijst', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test(IndicatorsIndex::class)
        ->assertSee(__('esg.setup.title'))
        ->assertSee(__('esg.setup.steps')[0], false);
});

it('toont esg-navigatie alleen wanneer module actief is', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true, 'trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee(__('common.nav.esg'));
});

it('verbergt esg-navigatie zonder module', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => false, 'trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertDontSee(__('common.nav.esg'));
});
