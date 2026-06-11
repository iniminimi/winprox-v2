<?php

use App\Livewire\Dashboard;
use App\Livewire\Issues\Index as IssuesIndex;
use App\Livewire\Locations\Index as LocationsIndex;
use App\Livewire\Pages\Calendar;
use App\Livewire\Tasks\Index as TasksIndex;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function setupOnboardingAdmin(): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $admin];
}

it('toont teams-onboarding met pulserende knop op het dashboard zonder teams', function () {
    [, $admin] = setupOnboardingAdmin();

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSee(__('dashboard.onboarding.teams.title'))
        ->assertSee(__('dashboard.onboarding.teams.text'))
        ->assertSee(__('dashboard.onboarding.teams.button'))
        ->assertSeeHtml('wp-badge-critical')
        ->assertSee(__('dashboard.welcome'))
        ->assertDontSee(__('dashboard.kpi.locations'));
});

it('toont categorieën-onboarding op meldingen zodra er een team is', function () {
    [$tenant, $admin] = setupOnboardingAdmin();
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(IssuesIndex::class)
        ->assertSee(__('dashboard.onboarding.categories.title'))
        ->assertSee(__('dashboard.onboarding.categories.button'))
        ->assertDontSee(__('issues.list.title'));
});

it('toont teams-onboarding op locaties, taken en kalender zonder teams', function () {
    [, $admin] = setupOnboardingAdmin();

    Livewire::actingAs($admin)->test(LocationsIndex::class)
        ->assertSee(__('dashboard.onboarding.teams.button'))
        ->assertDontSee(__('locations.add'));

    Livewire::actingAs($admin)->test(TasksIndex::class)
        ->assertSee(__('dashboard.onboarding.teams.button'))
        ->assertDontSee(__('tasks.list.title'));

    Livewire::actingAs($admin)->test(Calendar::class)
        ->assertSee(__('dashboard.onboarding.teams.button'))
        ->assertDontSee(__('calendar.title'));
});

it('verbergt de welkomstgids op het dashboard wanneer teams, workers, locaties en units bestaan', function () {
    [$tenant, $admin] = setupOnboardingAdmin();

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertDontSee(__('manual.getting_started.label'))
        ->assertSee(__('dashboard.kpi.locations'));
});
