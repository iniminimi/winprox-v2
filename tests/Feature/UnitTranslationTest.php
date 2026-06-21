<?php

use App\Actions\Communication\EnsureUnitTranslationSlotsAction;
use App\Actions\Communication\ImportUnitTranslationsAction;
use App\Actions\Communication\TranslateUnitAction;
use App\Actions\Locations\CreateUnitAction;
use App\Enums\UnitTranslationStatus;
use App\Livewire\Locations\Show;
use App\Livewire\Public\UnitPortal;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitTranslation;
use App\Models\User;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\FakeTranslationProvider;

afterEach(fn () => Tenancy::forget());

beforeEach(function () {
    app()->instance(TranslationProviderInterface::class, new FakeTranslationProvider);
});

it('seed pending vertaalrijen na aanmaken actieve unit', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'nl']);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $unit = app(CreateUnitAction::class)->handle($location, [
        'name' => 'Graafmachine TB210R',
        'description' => 'Magazijn zone B',
        'original_language' => 'nl',
    ], $tenant->id, $user->id);

    $rows = UnitTranslation::query()->where('unit_id', $unit->id)->get();
    $expectedLocales = collect(expectedTargetLocales('nl'))->sort()->values()->all();

    expect($rows)->toHaveCount(count($expectedLocales))
        ->and($rows->pluck('locale')->sort()->values()->all())->toBe($expectedLocales)
        ->and($rows->every(fn ($row) => $row->status === UnitTranslationStatus::Pending))->toBeTrue();
});

it('exporteert en importeert pending unitvertalingen', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Graafmachine',
        'description' => 'Zone B',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureUnitTranslationSlotsAction::class)->handle($unit);

    $exportItems = app(\App\Actions\Communication\ExportPendingUnitTranslationsAction::class)->handle();

    expect($exportItems)->toHaveCount(count(expectedTargetLocales('nl')))
        ->and($exportItems[0])->toHaveKeys(['unit_id', 'source_name', 'source_description', 'locale']);

    $imported = app(ImportUnitTranslationsAction::class)->handle([
        [
            'unit_id' => $unit->id,
            'locale' => 'en',
            'name' => 'Excavator',
            'description' => 'Zone B EN',
        ],
    ]);

    expect($imported)->toBe(1)
        ->and($unit->fresh()->localizedName('en'))->toBe('Excavator')
        ->and($unit->fresh()->localizedDescription('en'))->toBe('Zone B EN');
});

it('vertaalt unit via de provider', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Lift A',
        'description' => 'Verdieping 2',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureUnitTranslationSlotsAction::class)->handle($unit);
    app(TranslateUnitAction::class)->handle($unit, 'en');

    $row = UnitTranslation::query()->where('unit_id', $unit->id)->where('locale', 'en')->first();

    expect($row->status)->toBe(UnitTranslationStatus::Completed)
        ->and($row->name)->toBe('[en] Lift A')
        ->and($row->description)->toBe('[en] Verdieping 2');
});

it('weigert import van te lange unitnaam', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Lift',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureUnitTranslationSlotsAction::class)->handle($unit);

    expect(fn () => app(ImportUnitTranslationsAction::class)->handle([
        [
            'unit_id' => $unit->id,
            'locale' => 'en',
            'name' => str_repeat('x', 256),
        ],
    ]))->toThrow(ValidationException::class);
});

it('toont vertaalde unitnaam bij eerste portaal-load met locale cookie', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $category->teams()->sync([$team->id]);

    $unit = Unit::factory()->withQrToken('unit-token')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'name' => 'Graafmachine',
        'description' => 'Magazijn zone B',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureUnitTranslationSlotsAction::class)->handle($unit);
    app(ImportUnitTranslationsAction::class)->handle([
        [
            'unit_id' => $unit->id,
            'locale' => 'en',
            'name' => 'Excavator',
            'description' => 'Warehouse zone B',
        ],
    ]);

    Livewire::withCookies([\App\Support\ResolveAppLocale::COOKIE_NAME => 'en'])
        ->test(UnitPortal::class, ['token' => 'unit-token'])
        ->assertSet('locale', 'en')
        ->assertSee('Excavator')
        ->assertSee('Warehouse zone B');
});

it('toont vertaalde unitnaam in locatie-beheer volgens gebruikerstaal', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'en', 'role' => User::ROLE_ADMIN]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Graafmachine',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureUnitTranslationSlotsAction::class)->handle($unit);
    app(ImportUnitTranslationsAction::class)->handle([
        [
            'unit_id' => $unit->id,
            'locale' => 'en',
            'name' => 'Excavator',
        ],
    ]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\Locations\Show::class, ['location' => $location])
        ->assertSee('Excavator');
});

it('toont vertaal-preview in unit-bewerk-modal', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'en', 'role' => User::ROLE_ADMIN]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Graafmachine',
        'description' => 'Magazijn zone B',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureUnitTranslationSlotsAction::class)->handle($unit);
    app(TranslateUnitAction::class)->handle($unit, 'en');

    Livewire::actingAs($user)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->assertSet('previewLocale', 'en')
        ->assertSee('[en] Graafmachine')
        ->assertSee('[en] Magazijn zone B');
});

it('toont nog niet vertaald in unit-bewerk-modal wanneer vertaling ontbreekt', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'en', 'role' => User::ROLE_ADMIN]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Tenancy::actAs($tenant->id);

    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Graafmachine',
        'description' => 'Magazijn zone B',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureUnitTranslationSlotsAction::class)->handle($unit);

    Livewire::actingAs($user)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->assertSet('previewLocale', 'en')
        ->assertSee('Not translated yet');
});

it('toont vertaalde unitnaam in portaal bij taalwissel', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $category->teams()->sync([$team->id]);

    $unit = Unit::factory()->withQrToken('unit-token')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'name' => 'Graafmachine',
        'description' => 'Magazijn zone B',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureUnitTranslationSlotsAction::class)->handle($unit);
    app(ImportUnitTranslationsAction::class)->handle([
        [
            'unit_id' => $unit->id,
            'locale' => 'en',
            'name' => 'Excavator',
            'description' => 'Warehouse zone B',
        ],
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('switchLocale', 'en')
        ->assertSee('Excavator')
        ->assertSee('Warehouse zone B');
});
