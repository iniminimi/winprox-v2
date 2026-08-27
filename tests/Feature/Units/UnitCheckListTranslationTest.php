<?php

declare(strict_types=1);

use App\Actions\Communication\EnsureUnitCheckListTranslationSlotsAction;
use App\Actions\Communication\ExportPendingUnitCheckListTranslationsAction;
use App\Actions\Communication\ImportUnitCheckListTranslationsAction;
use App\Actions\Communication\TranslateUnitCheckListAction;
use App\Actions\Units\SaveUnitCheckListAction;
use App\Data\Units\SaveUnitCheckListData;
use App\Enums\UnitCheckListTranslationStatus;
use App\Models\Tenant;
use App\Models\UnitCheckList;
use App\Models\UnitCheckListItem;
use App\Models\UnitCheckListTranslation;
use App\Services\Translation\TranslationProviderInterface;
use App\Support\Tenancy;
use Tests\Support\FakeTranslationProvider;

afterEach(fn () => Tenancy::forget());

beforeEach(function () {
    app()->instance(TranslationProviderInterface::class, new FakeTranslationProvider);
});

function unitCheckListWithItems(Tenant $tenant, string $name = 'Schoonmaak'): UnitCheckList
{
    $list = UnitCheckList::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => $name,
        'original_language' => 'nl',
        'is_active' => true,
    ]);

    UnitCheckListItem::query()->create(['unit_check_list_id' => $list->id, 'label' => 'Vloer', 'sort_order' => 0]);
    UnitCheckListItem::query()->create(['unit_check_list_id' => $list->id, 'label' => 'WC', 'sort_order' => 1]);

    return $list->fresh(['items']);
}

it('seed pending vertaalrijen na aanmaken actieve checklist', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $list = app(SaveUnitCheckListAction::class)->handle(
        new SaveUnitCheckListData('Schoonmaak', ['Vloer', 'WC'], true, null, 'nl'),
        $tenant->id,
    );

    $rows = UnitCheckListTranslation::query()->where('unit_check_list_id', $list->id)->get();
    $expectedLocales = collect(expectedTargetLocales('nl'))->sort()->values()->all();

    expect($list->normalizedOriginalLanguage())->toBe('nl')
        ->and($rows)->toHaveCount(count($expectedLocales))
        ->and($rows->pluck('locale')->sort()->values()->all())->toBe($expectedLocales)
        ->and($rows->every(fn ($row) => $row->status === UnitCheckListTranslationStatus::Pending))->toBeTrue();
});

it('exporteert en importeert pending checklistvertalingen', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $list = unitCheckListWithItems($tenant);
    app(EnsureUnitCheckListTranslationSlotsAction::class)->handle($list);

    $exportItems = app(ExportPendingUnitCheckListTranslationsAction::class)->handle();

    expect($exportItems)->toHaveCount(count(expectedTargetLocales('nl')))
        ->and($exportItems[0])->toHaveKeys(['unit_check_list_id', 'source_name', 'source_items', 'locale'])
        ->and($exportItems[0]['source_items'])->toBe(['Vloer', 'WC']);

    $imported = app(ImportUnitCheckListTranslationsAction::class)->handle([
        [
            'unit_check_list_id' => $list->id,
            'locale' => 'en',
            'name' => 'Cleaning',
            'items' => ['Floor', 'Toilet'],
        ],
    ]);

    $fresh = $list->fresh(['items', 'translations']);

    expect($imported)->toBe(1)
        ->and($fresh->localizedName('en'))->toBe('Cleaning')
        ->and($fresh->localizedItemLabel('Vloer', 'en'))->toBe('Floor')
        ->and($fresh->localizedItemLabel('WC', 'en'))->toBe('Toilet');
});

it('weigert import met afwijkend aantal checklistpunten', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $list = unitCheckListWithItems($tenant);
    app(EnsureUnitCheckListTranslationSlotsAction::class)->handle($list);

    expect(fn () => app(ImportUnitCheckListTranslationsAction::class)->handle([
        [
            'unit_check_list_id' => $list->id,
            'locale' => 'en',
            'name' => 'Cleaning',
            'items' => ['Floor'],
        ],
    ]))->toThrow(Illuminate\Validation\ValidationException::class);
});

it('vertaalt checklist via de provider', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $list = unitCheckListWithItems($tenant);
    app(EnsureUnitCheckListTranslationSlotsAction::class)->handle($list);
    app(TranslateUnitCheckListAction::class)->handle($list, 'en');

    $row = UnitCheckListTranslation::query()
        ->where('unit_check_list_id', $list->id)
        ->where('locale', 'en')
        ->first();

    expect($row->status)->toBe(UnitCheckListTranslationStatus::Completed)
        ->and($row->name)->toBe('[en] Schoonmaak')
        ->and($row->items)->toBe(['[en] Vloer', '[en] WC']);
});

it('valt terug op de brontaal zolang de vertaling pending is', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $list = unitCheckListWithItems($tenant);
    app(EnsureUnitCheckListTranslationSlotsAction::class)->handle($list);

    $fresh = $list->fresh(['items', 'translations']);

    expect($fresh->localizedName('en'))->toBe('Schoonmaak')
        ->and($fresh->localizedItemLabel('Vloer', 'en'))->toBe('Vloer')
        ->and($fresh->localizedName('nl'))->toBe('Schoonmaak');
});

it('zet mislukte lege checklist-slots terug op pending bij ensure', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $list = unitCheckListWithItems($tenant);
    app(EnsureUnitCheckListTranslationSlotsAction::class)->handle($list);

    UnitCheckListTranslation::query()
        ->where('unit_check_list_id', $list->id)
        ->where('locale', 'en')
        ->update([
            'status' => UnitCheckListTranslationStatus::Failed->value,
            'name' => null,
            'items' => null,
        ]);

    app(EnsureUnitCheckListTranslationSlotsAction::class)->handle($list->fresh());

    $row = UnitCheckListTranslation::query()
        ->where('unit_check_list_id', $list->id)
        ->where('locale', 'en')
        ->first();

    expect($row->status)->toBe(UnitCheckListTranslationStatus::Pending);

    $exportItems = app(ExportPendingUnitCheckListTranslationsAction::class)->handle();
    $locales = collect($exportItems)
        ->where('unit_check_list_id', $list->id)
        ->pluck('locale')
        ->all();

    expect($locales)->toContain('en');
});

it('accepteert gelijke vertaling als voltooide checklistvertaling', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    app()->instance(TranslationProviderInterface::class, new class implements TranslationProviderInterface
    {
        public function translate(string $text, string $targetLanguage, ?string $sourceLanguage = null): string
        {
            return $text;
        }
    });

    $list = unitCheckListWithItems($tenant, 'Cleaning');
    app(EnsureUnitCheckListTranslationSlotsAction::class)->handle($list);
    app(TranslateUnitCheckListAction::class)->handle($list, 'en');

    $row = UnitCheckListTranslation::query()
        ->where('unit_check_list_id', $list->id)
        ->where('locale', 'en')
        ->first();

    expect($row->status)->toBe(UnitCheckListTranslationStatus::Completed)
        ->and($row->name)->toBe('Cleaning')
        ->and($row->items)->toBe(['Vloer', 'WC']);
});

it('zet vertalingen terug op pending na wijziging van naam of punten', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $list = unitCheckListWithItems($tenant);
    app(EnsureUnitCheckListTranslationSlotsAction::class)->handle($list);
    app(ImportUnitCheckListTranslationsAction::class)->handle([
        [
            'unit_check_list_id' => $list->id,
            'locale' => 'en',
            'name' => 'Cleaning',
            'items' => ['Floor', 'Toilet'],
        ],
    ]);

    app(SaveUnitCheckListAction::class)->handle(
        new SaveUnitCheckListData('Schoonmaak plus', ['Vloer', 'WC', 'Ramen'], true),
        $tenant->id,
        $list,
    );

    $row = UnitCheckListTranslation::query()
        ->where('unit_check_list_id', $list->id)
        ->where('locale', 'en')
        ->first();

    expect($row->status)->toBe(UnitCheckListTranslationStatus::Pending)
        ->and($row->name)->toBeNull()
        ->and($row->items)->toBeNull()
        ->and($list->fresh()->normalizedOriginalLanguage())->toBe('nl');
});
