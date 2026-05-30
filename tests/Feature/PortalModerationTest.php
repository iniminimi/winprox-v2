<?php

use App\Enums\TaskStatus;
use App\Livewire\Issues\Show;
use App\Livewire\Public\TeamPortal;
use App\Livewire\Public\UnitPortal;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('blokkeert het unit-portaal bij verlopen abonnement', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'is_active' => true,
    ]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'default_internal_team_id' => $team->id,
        'is_active' => true,
        'qr_token' => 'expired-unit',
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'expired-unit'])
        ->assertSet('inactiveReasonKey', 'portal.inactive.subscription_inactive')
        ->assertSee(__('portal.inactive.title'));
});

it('toont goedgekeurde melding-inhoud zonder blur op het unit-portaal', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'default_internal_team_id' => $team->id,
        'is_active' => true,
        'qr_token' => 'approved-unit',
    ]);

    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'unit_id' => $unit->id,
        'description' => 'Zichtbare goedgekeurde tekst.',
        'status' => TaskStatus::New,
        'approved_at' => now(),
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'approved-unit'])
        ->call('openSection', 'issues')
        ->assertSee('Zichtbare goedgekeurde tekst.')
        ->assertDontSee(__('portal.pending_review'));
});

it('blokkeert het team-portaal bij inactieve tenant', function () {
    $tenant = Tenant::factory()->create(['is_active' => false]);
    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'field_qr_token' => 'inactive-tenant-team',
    ]);

    Livewire::test(TeamPortal::class, ['token' => 'inactive-tenant-team'])
        ->assertSet('inactiveReasonKey', 'portal.inactive.tenant_inactive');
});

it('laat beheer een QR-melding goedkeuren en toont geen blur in beheer', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'description' => 'Te modereren QR-tekst.',
        'approved_at' => null,
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['issue' => $issue])
        ->assertSee(__('issues.pending_review'))
        ->assertSee('Te modereren QR-tekst.')
        ->assertDontSee('wp-pending-review')
        ->call('approve')
        ->assertHasNoErrors();

    expect($issue->fresh()->isApproved())->toBeTrue();
});
