<?php

namespace App\Livewire\Locations;

use App\Actions\Categories\SyncCategoryTeamsAction;
use App\Actions\Communication\ImportCategoryTranslationsAction;
use App\Actions\Communication\ImportLocationTranslationsAction;
use App\Actions\Locations\ActivateLocationAction;
use App\Actions\Locations\CreateCategoryAction;
use App\Actions\Locations\CreateLocationAction;
use App\Actions\Locations\DeactivateLocationAction;
use App\Actions\Locations\DeleteCategoryAction;
use App\Actions\Locations\DeleteLocationImportBatchAction;
use App\Actions\Locations\ImportLocationsAction;
use App\Actions\Locations\UpdateCategoryAction;
use App\Actions\Locations\UpdateLocationAction;
use App\Data\Locations\DeleteLocationImportBatchData;
use App\Data\Locations\ImportLocationsData;
use App\Http\Requests\Locations\ImportLocationsRequest;
use App\Http\Requests\Locations\StoreCategoryRequest;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Http\Requests\Locations\UpdateCategoryRequest;
use App\Http\Requests\Locations\UpdateLocationRequest;
use App\Livewire\Concerns\AppliesGpsCoordinatePair;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Support\Import\MinimalXlsxWriter;
use App\Support\Locations\LocationImportBatchRegistry;
use App\Support\Onboarding\TenantOnboardingState;
use App\Support\Platform\SupportTenantContext;
use App\Support\Tenancy;
use App\Support\Tenant\TenantWorkMenuAccess;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Index extends Component
{
    use AppliesGpsCoordinatePair;
    use WithFileUploads;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showInactive = false;

    public bool $showModal = false;

    public bool $showLocationsCsvImportModal = false;

    /** @var TemporaryUploadedFile|null */
    public $locationsCsvImportFile = null;

    /** @var list<string> */
    public array $locationsCsvImportErrors = [];

    public ?string $locationsImportNotice = null;

    public string $locationsImportNoticeType = 'success';

    public ?int $editingLocationId = null;

    public string $locationFormName = '';

    public string $locationFormStreet = '';

    public string $locationFormHouseNumber = '';

    public string $locationFormPostalCode = '';

    public string $locationFormCity = '';

    public string $locationFormCountryCode = 'BE';

    public string $locationFormNotes = '';

    public string $locationFormDdt = '';

    public string $locationFormLatitude = '';

    public string $locationFormLongitude = '';

    public string $locationPreviewLocale = '';

    public string $locationTranslationName = '';

    public bool $showCategoriesModal = false;

    #[Url(as: 'section')]
    public ?string $section = null;

    public ?int $editingCategoryId = null;

    public string $categoryName = '';

    public bool $categoryAllowGpsLocation = false;

    public bool $categoryIsReservable = false;

    public bool $categoryAllowUnitChecks = false;

    public bool $categoryAllowUnitMeasurements = false;

    public bool $categoryRequireReporterContact = false;

    public bool $categoryRequireReporterEmailVerification = false;

    public bool $categoryShowPreviousIssues = true;

    /** @var array<int, int> */
    public array $selectedCategoryTeamIds = [];

    public string $categoryPreviewLocale = '';

    public string $categoryTranslationName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Location::class);

        $categoryId = (int) request()->query('edit_category', 0);
        if ($categoryId > 0) {
            $this->section = 'categories';
            $this->openEditCategory($categoryId);
        }

        if (! in_array($this->section, ['categories', 'locations'], true)) {
            $this->section = 'locations';
        }

        if ((int) request()->query('create', 0) === 1 && $this->section === 'locations') {
            $this->openCreate();
        }
    }

    public function isCategoriesSection(): bool
    {
        return $this->section === 'categories';
    }

    public function updatedSearch(): void
    {
        // Re-render list.
    }

    public function openCreate(): void
    {
        $this->authorize('create', Location::class);
        $this->resetForm();
        $this->editingLocationId = null;
        $this->showModal = true;
    }

    public function openEdit(int $locationId): void
    {
        $location = Location::findOrFail($locationId);
        $this->authorize('update', $location);

        $this->editingLocationId = $location->id;
        $this->locationFormName = (string) $location->name;
        $this->locationFormStreet = (string) ($location->street ?? $location->address ?? '');
        $this->locationFormHouseNumber = (string) ($location->house_number ?? '');
        $this->locationFormPostalCode = (string) ($location->postal_code ?? '');
        $this->locationFormCity = (string) ($location->city ?? '');
        $this->locationFormCountryCode = (string) ($location->country_code ?? 'BE');
        $this->locationFormNotes = (string) ($location->notes ?? '');
        $this->locationFormDdt = (string) ($location->contractual_relationship_reference ?? '');
        $this->locationFormLatitude = $location->latitude !== null ? (string) $location->latitude : '';
        $this->locationFormLongitude = $location->longitude !== null ? (string) $location->longitude : '';
        $this->locationPreviewLocale = $this->defaultTranslationLocaleForLocation($location);
        $this->hydrateLocationTranslationInput($location->fresh('translations'));
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function updatedLocationPreviewLocale(): void
    {
        if ($this->editingLocationId === null) {
            $this->locationTranslationName = '';

            return;
        }

        $location = Location::query()
            ->with('translations')
            ->find($this->editingLocationId);

        $this->hydrateLocationTranslationInput($location);
    }

    public function saveLocationTranslationOverride(ImportLocationTranslationsAction $importLocationTranslations): void
    {
        if ($this->editingLocationId === null) {
            return;
        }

        $location = Location::query()
            ->with('translations')
            ->findOrFail($this->editingLocationId);

        $this->authorize('update', $location);

        if (! $location->is_active) {
            $this->addError('locationTranslationName', __('locations.errors.translation_requires_active'));

            return;
        }

        $validated = $this->validate([
            'locationTranslationName' => ['required', 'string', 'max:255'],
        ]);

        $locale = LocaleSupport::normalize($this->locationPreviewLocale);
        if ($locale === $location->normalizedOriginalLanguage()) {
            $this->addError('locationTranslationName', __('issues.errors.translation_same_as_source'));

            return;
        }

        $name = trim((string) $validated['locationTranslationName']);
        if ($name === '') {
            $this->addError('locationTranslationName', __('issues.errors.translation_import_invalid'));

            return;
        }

        try {
            $importLocationTranslations->handle([
                [
                    'location_id' => $location->id,
                    'locale' => $locale,
                    'name' => $name,
                ],
            ], (int) auth()->id());
        } catch (\Illuminate\Validation\ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                if (! is_array($messages)) {
                    continue;
                }

                foreach ($messages as $message) {
                    $this->addError('locationTranslationName', (string) $message);
                }
            }

            return;
        }

        $this->hydrateLocationTranslationInput($location->fresh('translations'));
        session()->flash('success', __('locations.flash.translation_saved'));
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(
        CreateLocationAction $createLocation,
        UpdateLocationAction $updateLocation,
    ): void {
        $payload = $this->locationFormPayload();

        if ($this->editingLocationId === null) {
            $this->authorize('create', Location::class);
            $validated = StoreLocationRequest::validatePayload($payload);
            $validated['original_language'] = auth()->user()?->locale;
            try {
                $createLocation->handle($validated, (int) auth()->user()->tenant_id, (int) auth()->id());
            } catch (InvalidArgumentException $e) {
                if ($e->getMessage() === 'location_limit_exceeded') {
                    $this->addError('locationFormName', __('locations.errors.location_limit'));

                    return;
                }

                throw $e;
            }
            session()->flash('success', __('locations.flash.created'));
        } else {
            $location = Location::findOrFail($this->editingLocationId);
            $this->authorize('update', $location);
            $validated = UpdateLocationRequest::validatePayload($payload);
            $updateLocation->handle($location, $validated, (int) auth()->id());
            session()->flash('success', __('locations.flash.updated'));
        }

        $this->closeModal();
    }

    public function deactivate(int $locationId, DeactivateLocationAction $deactivateLocation): void
    {
        $location = Location::findOrFail($locationId);
        $this->authorize('deactivate', $location);
        $deactivateLocation->handle($location, (int) auth()->id());
        session()->flash('success', __('locations.flash.deactivated'));
    }

    public function activate(int $locationId, ActivateLocationAction $activateLocation): void
    {
        $location = Location::findOrFail($locationId);
        $this->authorize('update', $location);
        $activateLocation->handle($location, (int) auth()->id());
        session()->flash('success', __('locations.flash.activated'));
    }

    public function openLocationsCsvImportModal(): void
    {
        $this->authorize('create', Location::class);
        abort_unless($this->viewerTenant()?->hasCsvLocationsImport() ?? false, 403);

        $this->locationsCsvImportFile = null;
        $this->locationsCsvImportErrors = [];
        $this->showLocationsCsvImportModal = true;
    }

    public function closeLocationsCsvImportModal(): void
    {
        $this->showLocationsCsvImportModal = false;
        $this->locationsCsvImportFile = null;
        $this->locationsCsvImportErrors = [];
    }

    public function importLocationsCsv(ImportLocationsAction $importLocations): void
    {
        $this->authorize('create', Location::class);
        abort_unless($this->viewerTenant()?->hasCsvLocationsImport() ?? false, 403);

        if ($this->locationsCsvImportFile === null) {
            $this->locationsCsvImportErrors = [__('locations.locations_csv.errors.file_required')];

            return;
        }

        $validator = Validator::make(
            ['file' => $this->locationsCsvImportFile],
            ImportLocationsRequest::getReusableRules(),
            ImportLocationsRequest::getReusableMessages()
        );

        if ($validator->fails()) {
            $this->locationsCsvImportErrors = $validator->errors()->all();

            return;
        }

        $result = $importLocations->handle(
            new ImportLocationsData(
                filePath: $this->locationsCsvImportFile->getRealPath(),
                originalName: $this->locationsCsvImportFile->getClientOriginalName(),
            ),
            (int) Tenancy::id(),
            (int) auth()->id(),
        );

        if ($result['success']) {
            session()->flash('success', __('locations.flash.locations_imported', ['count' => $result['count']]));
            $this->closeLocationsCsvImportModal();

            return;
        }

        $this->locationsCsvImportErrors = $result['errors'] ?? [__('locations.locations_csv.errors.failed')];
    }

    public function downloadLocationsSampleCsv(): StreamedResponse
    {
        $this->authorize('create', Location::class);
        abort_unless($this->viewerTenant()?->hasCsvLocationsImport() ?? false, 403);

        $headers = ImportLocationsAction::allHeaders();
        $sampleRow = [
            __('locations.import_sample.sample_location_name'),
            __('locations.import_sample.sample_street'),
            __('locations.import_sample.sample_house_number'),
            __('locations.import_sample.sample_postal_code'),
            __('locations.import_sample.sample_city'),
            'BE',
            __('locations.import_sample.sample_notes'),
            '',
            '',
            '',
        ];

        return response()->streamDownload(function () use ($headers, $sampleRow) {
            echo "\xEF\xBB\xBF";
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fputcsv($file, $sampleRow);
            fclose($file);
        }, 'locations-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadLocationsSampleXlsx(): StreamedResponse
    {
        $this->authorize('create', Location::class);
        abort_unless($this->viewerTenant()?->hasCsvLocationsImport() ?? false, 403);

        $rows = [
            ImportLocationsAction::allHeaders(),
            [
                __('locations.import_sample.sample_location_name'),
                __('locations.import_sample.sample_street'),
                __('locations.import_sample.sample_house_number'),
                __('locations.import_sample.sample_postal_code'),
                __('locations.import_sample.sample_city'),
                'BE',
                __('locations.import_sample.sample_notes'),
                '',
                '',
                '',
            ],
        ];

        return response()->streamDownload(function () use ($rows) {
            $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'locations-sample-'.uniqid('', true).'.xlsx';
            try {
                MinimalXlsxWriter::write($tempPath, $rows);
                readfile($tempPath);
            } finally {
                @unlink($tempPath);
            }
        }, 'locations-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function deleteLocationImportBatch(string $batchId, DeleteLocationImportBatchAction $deleteBatch): void
    {
        $this->authorize('create', Location::class);

        $tenantId = (int) Tenancy::id();
        $summary = LocationImportBatchRegistry::summary($tenantId, $batchId);

        if (! $summary['can_delete']) {
            $this->locationsImportNotice = __('locations.locations_import_history.nothing_deletable');
            $this->locationsImportNoticeType = 'error';

            return;
        }

        $result = $deleteBatch->handle(
            new DeleteLocationImportBatchData(importBatchId: $batchId),
            $tenantId,
            (int) auth()->id(),
        );

        if (! ($result['success'] ?? false)) {
            $this->locationsImportNotice = $result['errors'][0]
                ?? __('locations.locations_import_history.delete_failed');
            $this->locationsImportNoticeType = 'error';

            return;
        }

        $deleted = (int) ($result['deleted_count'] ?? 0);
        $preserved = (int) ($result['preserved_count'] ?? 0);

        if ($preserved > 0) {
            $this->locationsImportNotice = __('locations.locations_import_history.partially_deleted', [
                'deleted' => $deleted,
                'preserved' => $preserved,
            ]);
        } else {
            $this->locationsImportNotice = __('locations.locations_import_history.fully_deleted', [
                'count' => $deleted,
            ]);
        }
        $this->locationsImportNoticeType = 'success';
    }

    /**
     * @return array<string, string>
     */
    private function locationFormPayload(): array
    {
        return [
            'name' => $this->locationFormName,
            'street' => $this->locationFormStreet,
            'house_number' => $this->locationFormHouseNumber,
            'postal_code' => $this->locationFormPostalCode,
            'city' => $this->locationFormCity,
            'country_code' => $this->locationFormCountryCode,
            'notes' => $this->locationFormNotes,
            'contractual_relationship_reference' => $this->locationFormDdt,
            'latitude' => $this->locationFormLatitude,
            'longitude' => $this->locationFormLongitude,
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'locationFormName', 'locationFormStreet', 'locationFormHouseNumber', 'locationFormPostalCode', 'locationFormCity', 'locationFormNotes', 'locationFormDdt', 'locationFormLatitude', 'locationFormLongitude', 'editingLocationId',
            'locationPreviewLocale', 'locationTranslationName',
        ]);
        $this->editingLocationId = null;
        $this->locationFormCountryCode = 'BE';
        $this->resetErrorBag();
    }

    private function hydrateLocationTranslationInput(?Location $location): void
    {
        if ($location === null) {
            $this->locationTranslationName = '';

            return;
        }

        $locale = LocaleSupport::normalize($this->locationPreviewLocale);
        if ($locale === $location->normalizedOriginalLanguage()) {
            $locale = $this->defaultTranslationLocaleForLocation($location);
            $this->locationPreviewLocale = $locale;
        }

        $translation = $location->translations
            ->first(fn ($row) => $row->locale === $locale);

        $this->locationTranslationName = (string) ($translation?->name ?? '');
    }

    private function defaultTranslationLocaleForLocation(Location $location): string
    {
        $targets = LocaleSupport::targetLocalesForSource($location->normalizedOriginalLanguage());
        $preferred = LocaleSupport::normalize(auth()->user()?->locale ?? app()->getLocale());

        if (in_array($preferred, $targets, true)) {
            return $preferred;
        }

        return $targets[0] ?? $preferred;
    }

    public function openCategoriesModal(): void
    {
        $this->authorize('create', Category::class);
        $this->resetCategoryForm();
        $this->showCategoriesModal = true;
    }

    public function closeCategoriesModal(): void
    {
        $this->showCategoriesModal = false;
        $this->resetCategoryForm();
        $this->resetErrorBag();
    }

    public function canMutateOpenedCategory(): bool
    {
        if ($this->editingCategoryId === null) {
            return auth()->user()?->can('create', Category::class) === true;
        }

        $category = Category::query()->find($this->editingCategoryId);

        return $category instanceof Category
            && auth()->user()?->can('update', $category) === true;
    }

    public function openEditCategory(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);
        $this->authorize('view', $category);
        $this->editingCategoryId = (int) $category->id;
        $this->categoryName = (string) $category->name;
        $this->categoryAllowGpsLocation = (bool) $category->allow_gps_location;
        $this->categoryIsReservable = (bool) $category->is_reservable;
        $this->categoryAllowUnitChecks = (bool) $category->allow_unit_checks;
        $this->categoryAllowUnitMeasurements = (bool) $category->allow_unit_measurements;
        $this->categoryRequireReporterContact = (bool) $category->require_reporter_contact;
        $this->categoryRequireReporterEmailVerification = (bool) $category->require_reporter_email_verification;
        $this->categoryShowPreviousIssues = (bool) $category->show_previous_issues;
        $this->selectedCategoryTeamIds = $category->teams()->pluck('internal_teams.id')->toArray();
        $this->categoryPreviewLocale = $this->defaultTranslationLocaleForCategory($category);
        $this->hydrateCategoryTranslationInput($category->fresh('translations'));
        $this->showCategoriesModal = true;
        $this->resetErrorBag();
    }

    public function updatedCategoryPreviewLocale(): void
    {
        if ($this->editingCategoryId === null) {
            $this->categoryTranslationName = '';

            return;
        }

        $category = Category::query()
            ->with('translations')
            ->find($this->editingCategoryId);

        $this->hydrateCategoryTranslationInput($category);
    }

    public function cancelEditCategory(): void
    {
        $this->closeCategoriesModal();
    }

    public function saveCategory(CreateCategoryAction $createCategory, UpdateCategoryAction $updateCategory, SyncCategoryTeamsAction $syncTeams): void
    {
        if ($this->editingCategoryId === null) {
            $this->authorize('create', Category::class);
        } else {
            $this->authorize('update', Category::query()->findOrFail($this->editingCategoryId));
        }

        $tenantId = (int) auth()->user()->tenant_id;

        $rules = $this->editingCategoryId === null
            ? StoreCategoryRequest::ruleSet($tenantId)
            : UpdateCategoryRequest::ruleSetFor($tenantId, $this->editingCategoryId);

        $validated = $this->validate([
            'categoryName' => $rules['name'],
            'categoryAllowGpsLocation' => $rules['allow_gps_location'],
            'categoryIsReservable' => $rules['is_reservable'],
            'categoryAllowUnitChecks' => $rules['allow_unit_checks'],
            'categoryAllowUnitMeasurements' => $rules['allow_unit_measurements'],
            'categoryRequireReporterContact' => $rules['require_reporter_contact'],
            'categoryRequireReporterEmailVerification' => $rules['require_reporter_email_verification'],
            'categoryShowPreviousIssues' => $rules['show_previous_issues'],
            'selectedCategoryTeamIds' => 'required|array|min:1',
            'selectedCategoryTeamIds.*' => 'exists:internal_teams,id',
        ], [
            'categoryName.required' => __('locations.categories.errors.name_required'),
            'categoryName.unique' => __('locations.categories.errors.duplicate_name'),
            'selectedCategoryTeamIds.required' => __('locations.categories.errors.teams_required'),
            'selectedCategoryTeamIds.min' => __('locations.categories.errors.teams_required'),
        ]);

        $tenant = auth()->user()?->tenant;
        $currentCategory = $this->editingCategoryId !== null
            ? Category::query()->find($this->editingCategoryId)
            : null;

        if ($tenant instanceof Tenant) {
            if (! TenantWorkMenuAccess::mayEnableCategoryReservable(
                $tenant,
                (bool) $validated['categoryIsReservable'],
                (bool) ($currentCategory?->is_reservable ?? false),
            )) {
                $this->addError('categoryIsReservable', __('settings.work_menu.errors.reservations_disabled'));

                return;
            }

            if (! TenantWorkMenuAccess::mayEnableCategoryUnitMeasurements(
                $tenant,
                (bool) $validated['categoryAllowUnitMeasurements'],
                (bool) ($currentCategory?->allow_unit_measurements ?? false),
            )) {
                $this->addError('categoryAllowUnitMeasurements', __('settings.work_menu.errors.unit_measurements_disabled'));

                return;
            }
        }

        if ($this->editingCategoryId === null) {
            $category = $createCategory->handle($tenantId, [
                'name' => $validated['categoryName'],
                'allow_gps_location' => (bool) $validated['categoryAllowGpsLocation'],
                'is_reservable' => (bool) $validated['categoryIsReservable'],
                'allow_unit_checks' => (bool) $validated['categoryAllowUnitChecks'],
                'allow_unit_measurements' => (bool) $validated['categoryAllowUnitMeasurements'],
                'require_reporter_contact' => (bool) $validated['categoryRequireReporterContact'],
                'require_reporter_email_verification' => (bool) $validated['categoryRequireReporterEmailVerification'],
                'show_previous_issues' => (bool) $validated['categoryShowPreviousIssues'],
                'original_language' => auth()->user()?->locale,
            ], (int) auth()->id());
        } else {
            $category = Category::query()->findOrFail($this->editingCategoryId);
            $updateCategory->handle($category, [
                'name' => $validated['categoryName'],
                'allow_gps_location' => (bool) $validated['categoryAllowGpsLocation'],
                'is_reservable' => (bool) $validated['categoryIsReservable'],
                'allow_unit_checks' => (bool) $validated['categoryAllowUnitChecks'],
                'allow_unit_measurements' => (bool) $validated['categoryAllowUnitMeasurements'],
                'require_reporter_contact' => (bool) $validated['categoryRequireReporterContact'],
                'require_reporter_email_verification' => (bool) $validated['categoryRequireReporterEmailVerification'],
                'show_previous_issues' => (bool) $validated['categoryShowPreviousIssues'],
            ], (int) auth()->id());
        }

        $this->authorize('syncTeams', $category);
        $syncTeams->handle(
            $category,
            \App\Data\Categories\SyncCategoryTeamsData::fromRequest(['teams' => $validated['selectedCategoryTeamIds']]),
            auth()->user(),
        );

        $this->closeCategoriesModal();
    }

    public function saveCategoryTranslationOverride(ImportCategoryTranslationsAction $importCategoryTranslations): void
    {
        if ($this->editingCategoryId === null) {
            return;
        }

        $category = Category::query()
            ->with('translations')
            ->findOrFail($this->editingCategoryId);

        $this->authorize('update', $category);

        $validated = $this->validate([
            'categoryTranslationName' => ['required', 'string', 'max:255'],
        ]);

        $locale = LocaleSupport::normalize($this->categoryPreviewLocale);
        if ($locale === $category->normalizedOriginalLanguage()) {
            $this->addError('categoryTranslationName', __('issues.errors.translation_same_as_source'));

            return;
        }

        $name = trim((string) $validated['categoryTranslationName']);
        if ($name === '') {
            $this->addError('categoryTranslationName', __('issues.errors.translation_import_invalid'));

            return;
        }

        try {
            $importCategoryTranslations->handle([
                [
                    'category_id' => $category->id,
                    'locale' => $locale,
                    'name' => $name,
                ],
            ], (int) auth()->id());
        } catch (\Illuminate\Validation\ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                if (! is_array($messages)) {
                    continue;
                }

                foreach ($messages as $message) {
                    $this->addError('categoryTranslationName', (string) $message);
                }
            }

            return;
        }

        $this->hydrateCategoryTranslationInput($category->fresh('translations'));
        session()->flash('success', __('locations.categories.flash.translation_saved'));
    }

    public function deleteCategory(int $categoryId, DeleteCategoryAction $deleteCategory): void
    {
        $category = Category::query()->findOrFail($categoryId);
        $this->authorize('delete', $category);
        $deleteCategory->handle($category, (int) auth()->id());
    }

    private function resetCategoryForm(): void
    {
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->categoryAllowGpsLocation = false;
        $this->categoryIsReservable = false;
        $this->categoryAllowUnitChecks = false;
        $this->categoryAllowUnitMeasurements = false;
        $this->categoryRequireReporterContact = false;
        $this->categoryRequireReporterEmailVerification = false;
        $this->categoryShowPreviousIssues = true;
        $this->selectedCategoryTeamIds = [];
        $this->categoryPreviewLocale = '';
        $this->categoryTranslationName = '';
    }

    private function hydrateCategoryTranslationInput(?Category $category): void
    {
        if ($category === null) {
            $this->categoryTranslationName = '';

            return;
        }

        $locale = LocaleSupport::normalize($this->categoryPreviewLocale);
        if ($locale === $category->normalizedOriginalLanguage()) {
            $locale = $this->defaultTranslationLocaleForCategory($category);
            $this->categoryPreviewLocale = $locale;
        }

        $translation = $category->translations
            ->first(fn ($row) => $row->locale === $locale);

        $this->categoryTranslationName = (string) ($translation?->name ?? '');
    }

    private function defaultTranslationLocaleForCategory(Category $category): string
    {
        $targets = LocaleSupport::targetLocalesForSource($category->normalizedOriginalLanguage());
        $preferred = LocaleSupport::normalize(auth()->user()?->locale ?? app()->getLocale());

        if (in_array($preferred, $targets, true)) {
            return $preferred;
        }

        return $targets[0] ?? $preferred;
    }

    public function render()
    {
        $isCategories = $this->isCategoriesSection();
        $term = trim($this->search);

        $locations = $isCategories
            ? collect()
            : Location::query()
                ->withCount('units')
                ->when(! $this->showInactive, fn ($q) => $q->where('is_active', true))
                ->when($term !== '', function ($q) use ($term) {
                    $like = '%'.$term.'%';
                    $q->where(function ($query) use ($like) {
                        $query->where('name', 'like', $like)
                            ->orWhere('street', 'like', $like)
                            ->orWhere('house_number', 'like', $like)
                            ->orWhere('postal_code', 'like', $like)
                            ->orWhere('city', 'like', $like)
                            ->orWhere('address', 'like', $like);
                    });
                })
                ->orderBy('name')
                ->get();

        $hasInactiveLocations = $isCategories
            ? false
            : Location::query()->where('is_active', false)->exists();

        $hasAnyLocation = $isCategories
            ? true
            : Location::query()->exists();

        $categoriesEnabled = Schema::hasTable('categories');

        $teams = $isCategories
            ? InternalTeam::query()
                ->where('is_active', true)
                ->with('translations')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'original_language'])
            : collect();

        $categories = $categoriesEnabled
            ? Category::query()->with('translations')->orderBy('name')->get(['id', 'name', 'original_language', 'tenant_id'])
            : collect();

        $categoryTranslationLocales = config('locales.labels', []);
        if ($this->showCategoriesModal && $this->editingCategoryId !== null) {
            $editingCategory = Category::query()->find($this->editingCategoryId);

            if ($editingCategory !== null) {
                $sourceLocale = $editingCategory->normalizedOriginalLanguage();
                $categoryTranslationLocales = array_filter(
                    $categoryTranslationLocales,
                    fn (string $label, string $code): bool => $code !== $sourceLocale,
                    ARRAY_FILTER_USE_BOTH,
                );
            }
        }

        $locationTranslationLocales = config('locales.labels', []);
        $editingLocation = null;
        $viewerTenant = $this->viewerTenant();
        if ($this->showModal && $this->editingLocationId !== null) {
            $editingLocation = Location::query()->find($this->editingLocationId);

            if ($editingLocation !== null) {
                $sourceLocale = $editingLocation->normalizedOriginalLanguage();
                $locationTranslationLocales = array_filter(
                    $locationTranslationLocales,
                    fn (string $label, string $code): bool => $code !== $sourceLocale,
                    ARRAY_FILTER_USE_BOTH,
                );
            }
        }

        return view('livewire.locations.index', [
            'locations' => $locations,
            'hasAnyLocation' => $hasAnyLocation,
            'hasInactiveLocations' => $hasInactiveLocations,
            'teams' => $teams,
            'categories' => $categories,
            'editingLocation' => $editingLocation,
            'locationTranslationLocales' => $locationTranslationLocales,
            'categoryTranslationLocales' => $categoryTranslationLocales,
            'onboarding' => TenantOnboardingState::current(),
            'canMutateOpenedCategory' => $this->canMutateOpenedCategory(),
            'workMenuReservationsEnabled' => $viewerTenant?->workMenuReservationsEnabled() ?? true,
            'workMenuUnitMeasurementsEnabled' => $viewerTenant?->workMenuUnitMeasurementsEnabled() ?? true,
            'presenceComplianceEnabled' => (bool) ($viewerTenant?->presenceComplianceEnabled()),
            'gpsWorkVisitsEnabled' => (bool) ($viewerTenant?->allowsGpsWorkVisits()),
            'canImportLocationsCsv' => $viewerTenant?->hasCsvLocationsImport() ?? false,
            'locationImportBatches' => $isCategories
                ? collect()
                : LocationImportBatchRegistry::recentBatchesForTenant((int) Tenancy::id())
                    ->map(fn (array $batch) => array_merge(
                        $batch,
                        LocationImportBatchRegistry::summary((int) Tenancy::id(), $batch['batch_id']),
                    )),
        ]);
    }

    private function viewerTenant(): ?Tenant
    {
        $user = auth()->user();
        if ($user?->tenant instanceof Tenant) {
            return $user->tenant;
        }

        if ($user?->is_superuser && SupportTenantContext::isActive()) {
            return Tenant::query()->find(SupportTenantContext::activeTenantId());
        }

        return null;
    }
}
