<?php

use App\Actions\Communication\EnsureLocationTranslationSlotsAction;
use App\Actions\Communication\ImportLocationTranslationsAction;
use App\Actions\Locations\CreateLocationAction;
use App\Enums\LocationTranslationStatus;
use App\Livewire\Public\UnitPortal;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\LocationTranslation;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('seed pending vertaalrijen na aanmaken actieve locatie', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'nl']);
    Tenancy::actAs($tenant->id);

    $location = app(CreateLocationAction::class)->handle([
        'name' => 'Hoofddepot Deinze',
        'original_language' => 'nl',
    ], $tenant->id, $user->id);

    $rows = LocationTranslation::query()->where('location_id', $location->id)->get();
    $expectedLocales = collect(expectedTargetLocales('nl'))->sort()->values()->all();

    expect($rows)->toHaveCount(count($expectedLocales))
        ->and($rows->pluck('locale')->sort()->values()->all())->toBe($expectedLocales)
        ->and($rows->every(fn ($row) => $row->status === LocationTranslationStatus::Pending))->toBeTrue();
});

it('exporteert en importeert pending locatievertalingen', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hoofddepot Deinze',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureLocationTranslationSlotsAction::class)->handle($location);

    $exportItems = app(\App\Actions\Communication\ExportPendingLocationTranslationsAction::class)->handle();

    expect($exportItems)->toHaveCount(count(expectedTargetLocales('nl')))
        ->and($exportItems[0])->toHaveKeys(['location_id', 'source_name', 'locale']);

    $imported = app(ImportLocationTranslationsAction::class)->handle([
        [
            'location_id' => $location->id,
            'locale' => 'en',
            'name' => 'Main depot Deinze',
        ],
    ]);

    expect($imported)->toBe(1)
        ->and($location->fresh()->localizedName('en'))->toBe('Main depot Deinze');
});

it('toont vertaalde locatienaam in unit-portaal bij taalwissel', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hoofddepot',
        'original_language' => 'nl',
        'is_active' => true,
    ]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $category->teams()->sync([$team->id]);

    $unit = Unit::factory()->withQrToken('unit-token')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'category_id' => $category->id,
        'name' => 'Graafmachine',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureLocationTranslationSlotsAction::class)->handle($location);
    app(ImportLocationTranslationsAction::class)->handle([
        [
            'location_id' => $location->id,
            'locale' => 'en',
            'name' => 'Main depot',
        ],
    ]);

    Livewire::test(UnitPortal::class, ['token' => 'unit-token'])
        ->call('switchLocale', 'en')
        ->assertSee('Main depot')
        ->assertSee('Graafmachine');
});

it('weigert import van te lange locatienaam', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Depot',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureLocationTranslationSlotsAction::class)->handle($location);

    expect(fn () => app(ImportLocationTranslationsAction::class)->handle([
        [
            'location_id' => $location->id,
            'locale' => 'en',
            'name' => str_repeat('x', 256),
        ],
    ]))->toThrow(ValidationException::class);
});

it('exporteert locatievertalingen via translation:export', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Magazijn',
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    app(EnsureLocationTranslationSlotsAction::class)->handle($location);

    $path = storage_path('app/exports/translations.json');

    \Illuminate\Support\Facades\Artisan::call('translation:export');

    $payload = json_decode(\Illuminate\Support\Facades\File::get($path), true);
    $locationIds = collect($payload['items'] ?? [])->pluck('location_id')->filter()->all();

    expect($locationIds)->toContain($location->id);
});
