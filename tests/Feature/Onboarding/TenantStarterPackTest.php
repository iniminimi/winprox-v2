<?php

declare(strict_types=1);

use App\Actions\Onboarding\ApplyTenantStarterPackAction;
use App\Actions\Onboarding\RemoveTenantStarterPackAction;
use App\Data\Onboarding\ApplyTenantStarterPackData;
use App\Enums\InternalTeamTranslationStatus;
use App\Enums\TenantStarterPackType;
use App\Enums\UnitTranslationStatus;
use App\Livewire\Dashboard;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Onboarding\TenantOnboardingState;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function setupStarterPackAdmin(?string $locale = 'nl'): array
{
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create([
        'tenant_id' => $tenant->id,
        'locale' => $locale,
    ]);

    return [$tenant, $admin];
}

it('maakt een hotel-starttemplate met namen in alle talen', function () {
    [$tenant, $admin] = setupStarterPackAdmin('nl');

    $payload = app(ApplyTenantStarterPackAction::class)->handle(
        $tenant,
        ApplyTenantStarterPackData::fromValidated(['starterPackType' => 'hotel'], 'nl'),
        $admin,
    );

    $tenant->refresh();

    expect($tenant->starter_pack_key)->toBe('hotel')
        ->and($payload['unit_ids'])->toHaveCount(3)
        ->and(InternalTeam::query()->count())->toBe(2)
        ->and(Category::query()->count())->toBe(3)
        ->and(Location::query()->count())->toBe(1)
        ->and(Unit::query()->count())->toBe(3);

    $cleaning = InternalTeam::query()->where('name', 'Schoonmaak')->first();
    expect($cleaning)->not->toBeNull()
        ->and($cleaning->original_language)->toBe('nl');

    $en = $cleaning->translations()->where('locale', 'en')->first();
    expect($en?->name)->toBe('Cleaning')
        ->and($en?->status)->toBe(InternalTeamTranslationStatus::Completed);

    $room = Unit::query()->where('name', 'Kamer 001')->first();
    expect($room)->not->toBeNull();
    $roomEn = $room->translations()->where('locale', 'en')->first();
    expect($roomEn?->name)->toBe('Room 001')
        ->and($roomEn?->status)->toBe(UnitTranslationStatus::Completed);

    Tenancy::actAs($tenant->id);
    $state = TenantOnboardingState::current();
    expect($state->showTeamsBanner())->toBeFalse()
        ->and($state->showCategoriesBanner())->toBeFalse()
        ->and($state->showLocationsBanner())->toBeFalse()
        ->and($state->showUnitsBanner())->toBeFalse()
        ->and($state->canApplyStarterPack())->toBeFalse();
});

it('weigert een starttemplate wanneer de werkruimte niet leeg is', function () {
    [$tenant, $admin] = setupStarterPackAdmin();
    InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    app(ApplyTenantStarterPackAction::class)->handle(
        $tenant,
        ApplyTenantStarterPackData::fromValidated(['starterPackType' => 'industry'], 'nl'),
        $admin,
    );
})->throws(\Illuminate\Validation\ValidationException::class);

it('verwijdert het starttemplate wanneer er geen meldingen zijn', function () {
    [$tenant, $admin] = setupStarterPackAdmin();

    app(ApplyTenantStarterPackAction::class)->handle(
        $tenant,
        ApplyTenantStarterPackData::fromValidated(['starterPackType' => 'municipality'], 'nl'),
        $admin,
    );

    app(RemoveTenantStarterPackAction::class)->handle($tenant->fresh(), $admin);

    $tenant->refresh();

    expect($tenant->starter_pack_key)->toBeNull()
        ->and(InternalTeam::query()->count())->toBe(0)
        ->and(Category::query()->count())->toBe(0)
        ->and(Location::query()->count())->toBe(0)
        ->and(Unit::query()->count())->toBe(0);
});

it('houdt het starttemplate wanneer er al een melding op een unit staat', function () {
    [$tenant, $admin] = setupStarterPackAdmin();

    app(ApplyTenantStarterPackAction::class)->handle(
        $tenant,
        ApplyTenantStarterPackData::fromValidated(['starterPackType' => 'hospital'], 'nl'),
        $admin,
    );

    $unit = Unit::query()->first();
    Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $unit->location_id,
        'unit_id' => $unit->id,
        'approved_at' => now(),
    ]);

    expect(fn () => app(RemoveTenantStarterPackAction::class)->handle($tenant->fresh(), $admin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $tenant->refresh();
    expect($tenant->starter_pack_key)->toBe('hospital')
        ->and(Unit::query()->count())->toBe(3);
});

it('toont de starttemplate-knop op het dashboard van een lege werkruimte', function () {
    [, $admin] = setupStarterPackAdmin();

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSee(__('dashboard.starter_pack.help_button'))
        ->assertSee(__('dashboard.onboarding.teams.button'));
});

it('laadt een starttemplate via het dashboard en toont het resultaat', function () {
    [, $admin] = setupStarterPackAdmin();

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->call('openStarterPackModal')
        ->set('starterPackType', TenantStarterPackType::Hotel->value)
        ->call('applyStarterPack')
        ->assertHasNoErrors()
        ->assertSee(__('dashboard.starter_pack.result_title'))
        ->assertSee(__('starter_pack.types.hotel'))
        ->assertSee(__('dashboard.starter_pack.remove'))
        ->assertDontSee(__('dashboard.starter_pack.help_button'));
});
