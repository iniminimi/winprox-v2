<?php

use App\Livewire\Issues\Show as IssueShow;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('toont Unit openen naast de headline op meldingdetail', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Hoofdkantoor']);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Kamer 12',
    ]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test(IssueShow::class, ['issue' => $issue])
        ->assertSee(__('issues.show.open_unit'))
        ->assertSeeHtml('unit_id='.$unit->id);
});

it('toont Unit openen niet voor inspectieronde zonder unit', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => null,
        'is_recurring' => true,
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test(IssueShow::class, ['issue' => $issue])
        ->assertDontSee(__('issues.show.open_unit'));
});
