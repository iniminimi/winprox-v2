<?php

use App\Enums\IssueSource;
use App\Livewire\Issues\Index as IssueIndex;
use App\Models\EsgIndicator;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('koppelt een actieve indicator aan een terugkerende melding', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $indicator = EsgIndicator::factory()->numeric('m3')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Gas m3',
    ]);

    Tenancy::actAs($tenant->id);

    Livewire::actingAs($user)
        ->test(IssueIndex::class)
        ->call('openCreateModal')
        ->set('location_id', $location->id)
        ->set('unit_id', $unit->id)
        ->set('description', 'Maandelijkse gasmeter Blok B')
        ->set('is_recurring', true)
        ->set('recurrence_interval_value', 1)
        ->set('recurrence_interval_unit', 'month')
        ->set('recurrence_lead_days', 7)
        ->set('recurrence_first_due_date', now()->addWeek()->toDateString())
        ->set('esg_indicator_id', $indicator->id)
        ->call('saveCreateStepOne')
        ->assertHasNoErrors()
        ->set('internal_team_id', $team->id)
        ->call('saveCreateStepTwo');

    $issue = Issue::query()->first();

    expect($issue)->not->toBeNull()
        ->and($issue->is_recurring)->toBeTrue()
        ->and($issue->esg_indicator_id)->toBe($indicator->id)
        ->and($issue->unit_id)->toBe($unit->id)
        ->and($issue->source)->toBe(IssueSource::Manager);
});

it('weigert een indicator zonder unit bij terugkerende melding', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $indicator = EsgIndicator::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    Livewire::actingAs($user)
        ->test(IssueIndex::class)
        ->call('openCreateModal')
        ->set('location_id', $location->id)
        ->set('description', 'Gasmeting zonder unit')
        ->set('is_recurring', true)
        ->set('recurrence_interval_value', 1)
        ->set('recurrence_interval_unit', 'month')
        ->set('recurrence_lead_days', 7)
        ->set('recurrence_first_due_date', now()->addWeek()->toDateString())
        ->set('esg_indicator_id', $indicator->id)
        ->call('saveCreateStepOne')
        ->assertHasErrors(['unit_id']);

    expect(Issue::count())->toBe(0);
});

it('slaat geen indicator op bij niet-terugkerende melding', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $indicator = EsgIndicator::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    Livewire::actingAs($user)
        ->test(IssueIndex::class)
        ->call('openCreateModal')
        ->set('location_id', $location->id)
        ->set('description', 'Eenmalige melding')
        ->set('is_recurring', false)
        ->set('esg_indicator_id', $indicator->id)
        ->call('saveCreateStepOne')
        ->assertHasErrors(['esg_indicator_id']);

    Livewire::actingAs($user)
        ->test(IssueIndex::class)
        ->call('openCreateModal')
        ->set('location_id', $location->id)
        ->set('description', 'Eenmalige melding zonder indicator')
        ->set('is_recurring', false)
        ->call('saveCreateStepOne')
        ->assertHasNoErrors()
        ->set('internal_team_id', $team->id)
        ->call('saveCreateStepTwo');

    expect(Issue::first()->esg_indicator_id)->toBeNull();
});

it('verbergt indicator-keuze zonder esg-module', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => false]);
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    Livewire::actingAs($user)
        ->test(IssueIndex::class)
        ->call('openCreateModal')
        ->set('is_recurring', true)
        ->assertDontSee(__('issues.create.esg_indicator'));
});

it('erft indicator via issue op taak voor recurring cycle', function () {
    $tenant = Tenant::factory()->create(['has_esg_module' => true]);
    $indicator = EsgIndicator::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'is_recurring' => true,
        'recurrence_active' => true,
        'recurrence_next_due_at' => now()->addDays(3),
        'recurrence_lead_days' => 30,
        'esg_indicator_id' => $indicator->id,
    ]);

    expect($issue->fresh()->esgIndicator?->id)->toBe($indicator->id);
});
