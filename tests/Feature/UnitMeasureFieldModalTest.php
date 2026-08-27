<?php

use App\Livewire\UnitMeasurements\FieldsIndex;
use App\Models\Tenant;
use App\Models\UnitMeasureField;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

it('closes the measure-field modal after a successful save', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

    Tenancy::actAs($tenant);

    Livewire::actingAs($user)
        ->test(FieldsIndex::class)
        ->call('openCreateModal')
        ->assertSet('showModal', true)
        ->set('name', 'Kilometerstand')
        ->set('type', 'numeric')
        ->set('unitOfMeasure', 'km')
        ->call('save')
        ->assertSet('showModal', false)
        ->assertHasNoErrors();

    expect(UnitMeasureField::query()->where('name', 'Kilometerstand')->exists())->toBeTrue();
});
