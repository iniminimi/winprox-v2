<?php

use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Onboarding\TenantOnboardingState;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('toont teams-onboarding wanneer er nog geen teams zijn', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $state = TenantOnboardingState::current();

    expect($state->showTeamsBanner())->toBeTrue()
        ->and($state->showCategoriesBanner())->toBeFalse()
        ->and($state->showWelcomeGuide)->toBeTrue()
        ->and($state->blocksDashboardMain())->toBeTrue();
});

it('toont categorieën-onboarding zodra er een team is maar nog geen categorieën', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $state = TenantOnboardingState::current();

    expect($state->showTeamsBanner())->toBeFalse()
        ->and($state->showCategoriesBanner())->toBeTrue()
        ->and($state->showWelcomeGuide)->toBeFalse()
        ->and($state->blocksDashboardMain())->toBeTrue();
});

it('toont locaties-onboarding op functionele paginas wanneer teams bestaan maar locaties of units ontbreken', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);

    $state = TenantOnboardingState::current();

    expect($state->showCategoriesOrLocationsBanner())->toBeTrue()
        ->and($state->showCategoriesBanner())->toBeFalse()
        ->and($state->showLocationsBanner())->toBeTrue()
        ->and($state->blocksDashboardMain())->toBeTrue()
        ->and($state->showWelcomeGuide)->toBeTrue();
});

it('is klaar voor het dashboard wanneer teams, workers, categorieën, locaties, units en een clock point bestaan', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);
    ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $state = TenantOnboardingState::current();

    expect($state->showTeamsBanner())->toBeFalse()
        ->and($state->showCategoriesBanner())->toBeFalse()
        ->and($state->showCategoriesOrLocationsBanner())->toBeFalse()
        ->and($state->showClockPointBanner())->toBeFalse()
        ->and($state->showWelcomeGuide)->toBeFalse()
        ->and($state->blocksDashboardMain())->toBeFalse();
});

it('toont clock point-onboarding wanneer facility-basis klaar is maar nog geen clock point', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    Worker::factory()->create(['tenant_id' => $tenant->id]);
    Category::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->create(['tenant_id' => $tenant->id, 'location_id' => $location->id]);

    $state = TenantOnboardingState::current();

    expect($state->showClockPointBanner())->toBeTrue()
        ->and($state->blocksDashboardMain())->toBeTrue()
        ->and($state->showWelcomeGuide)->toBeTrue();
});
