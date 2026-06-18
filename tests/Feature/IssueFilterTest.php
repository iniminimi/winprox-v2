<?php

use App\Enums\TaskStatus;
use App\Livewire\Issues\Index;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function seedFilterableIssues(Tenant $tenant): array
{
    $teamA = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Technische dienst']);
    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Schoonmaak']);
    Category::factory()->create(['tenant_id' => $tenant->id]);

    $location = \App\Models\Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = \App\Models\Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);

    $open = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'status' => TaskStatus::New,
        'approved_at' => now(),
        'description' => 'Kraan lekt in de keuken',
    ]);
    Task::factory()->create(['tenant_id' => $tenant->id, 'issue_id' => $open->id, 'internal_team_id' => $teamA->id, 'status' => TaskStatus::New]);

    $closed = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'status' => TaskStatus::Done,
        'approved_at' => now(),
        'description' => 'Lamp stuk in het magazijn',
    ]);
    Task::factory()->create(['tenant_id' => $tenant->id, 'issue_id' => $closed->id, 'internal_team_id' => $teamB->id, 'status' => TaskStatus::Done]);

    return [$teamA, $teamB];
}

it('groepeert en toont alle meldingen zonder filter', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    seedFilterableIssues($tenant);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('Kraan lekt in de keuken')
        ->assertSee('Lamp stuk in het magazijn');
});

it('filtert meldingen op status', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    seedFilterableIssues($tenant);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('statusFilter', TaskStatus::New->value)
        ->call('applyFilters')
        ->assertSee('Kraan lekt in de keuken')
        ->assertDontSee('Lamp stuk in het magazijn');
});

it('filtert meldingen op team', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    [$teamA] = seedFilterableIssues($tenant);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('teamFilter', $teamA->id)
        ->call('applyFilters')
        ->assertSee('Kraan lekt in de keuken')
        ->assertDontSee('Lamp stuk in het magazijn');
});

it('filtert meldingen op zoekterm', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    seedFilterableIssues($tenant);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('search', 'magazijn')
        ->call('applyFilters')
        ->assertSee('Lamp stuk in het magazijn')
        ->assertDontSee('Kraan lekt in de keuken');
});

it('zoekt meldingen op locatiestraat', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);

    $location = \App\Models\Location::factory()->create([
        'tenant_id' => $tenant->id,
        'street' => 'Industrieweg',
        'house_number' => '99',
    ]);
    $unit = \App\Models\Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);
    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'status' => TaskStatus::New,
        'approved_at' => now(),
        'description' => 'Melding op industrieweg',
    ]);
    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => TaskStatus::New,
        'approved_at' => now(),
        'description' => 'Andere melding',
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('search', 'Industrieweg')
        ->call('applyFilters')
        ->assertSee('Melding op industrieweg')
        ->assertDontSee('Andere melding');
});
