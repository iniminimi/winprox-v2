<?php

use App\Actions\Communication\EnsureCategoryTranslationSlotsAction;
use App\Actions\Communication\ExportPendingCategoryTranslationsAction;
use App\Actions\Communication\ImportCategoryTranslationsAction;
use App\Actions\Communication\TranslateCategoryAction;
use App\Actions\Locations\CreateCategoryAction;
use App\Enums\CategoryTranslationStatus;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeTranslationProvider;

afterEach(fn () => Tenancy::forget());

beforeEach(function () {
    app()->instance(TranslationProviderInterface::class, new FakeTranslationProvider);
});

it('seed pending vertaalrijen na aanmaken categorie', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'locale' => 'nl']);
    Tenancy::actAs($tenant->id);

    $category = app(CreateCategoryAction::class)->handle($tenant->id, [
        'name' => 'Graafmachines',
        'original_language' => 'nl',
    ], $user->id);

    $rows = CategoryTranslation::query()->where('category_id', $category->id)->get();
    $expectedLocales = collect(expectedTargetLocales('nl'))->sort()->values()->all();

    expect($rows)->toHaveCount(count($expectedLocales))
        ->and($rows->pluck('locale')->sort()->values()->all())->toBe($expectedLocales)
        ->and($rows->every(fn ($row) => $row->status === CategoryTranslationStatus::Pending))->toBeTrue();
});

it('exporteert en importeert pending categorievertalingen', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Graafmachines',
        'original_language' => 'nl',
    ]);

    app(EnsureCategoryTranslationSlotsAction::class)->handle($category);

    $exportItems = app(ExportPendingCategoryTranslationsAction::class)->handle();

    expect($exportItems)->toHaveCount(count(expectedTargetLocales('nl')))
        ->and($exportItems[0])->toHaveKeys(['category_id', 'source_name', 'locale']);

    $imported = app(ImportCategoryTranslationsAction::class)->handle([
        [
            'category_id' => $category->id,
            'locale' => 'en',
            'name' => 'Excavators',
        ],
    ]);

    expect($imported)->toBe(1)
        ->and($category->fresh()->localizedName('en'))->toBe('Excavators');
});

it('vertaalt categorie via de provider', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Graafmachines',
        'original_language' => 'nl',
    ]);

    app(EnsureCategoryTranslationSlotsAction::class)->handle($category);
    app(TranslateCategoryAction::class)->handle($category, 'en');

    $row = CategoryTranslation::query()->where('category_id', $category->id)->where('locale', 'en')->first();

    expect($row->status)->toBe(CategoryTranslationStatus::Completed)
        ->and($row->name)->toBe('[en] Graafmachines')
        ->and($category->fresh()->localizedName('en'))->toBe('[en] Graafmachines');
});

it('weigert import van te lange categorienaam', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $category = Category::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Graafmachines',
        'original_language' => 'nl',
    ]);

    app(EnsureCategoryTranslationSlotsAction::class)->handle($category);

    expect(fn () => app(ImportCategoryTranslationsAction::class)->handle([
        [
            'category_id' => $category->id,
            'locale' => 'en',
            'name' => str_repeat('x', 256),
        ],
    ]))->toThrow(ValidationException::class);
});
