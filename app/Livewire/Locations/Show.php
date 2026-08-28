<?php

namespace App\Livewire\Locations;

use App\Actions\Locations\BulkCreateUnitsAction;
use App\Actions\Locations\CreateUnitAction;
use App\Actions\Locations\DeactivateLocationAction;
use App\Actions\Locations\ActivateUnitAction;
use App\Actions\Locations\DeactivateUnitAction;
use App\Actions\Locations\DeleteUnitAction;
use App\Actions\Locations\DeleteUnitBulkBatchAction;
use App\Actions\Locations\UpdateLocationAction;
use App\Actions\Locations\UpdateUnitAction;
use App\Actions\QrCodes\DeleteQrLinkPhotoAction;
use App\Actions\Communication\ImportLocationTranslationsAction;
use App\Actions\Communication\ImportUnitTranslationsAction;
use App\Actions\Units\DeleteImportBatchAction;
use App\Actions\Units\ImportUnitsAction;
use App\Data\Units\DeleteImportBatchData;
use App\Data\Units\ImportUnitsData;
use App\Http\Requests\Locations\BulkCreateUnitsRequest;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Http\Requests\Locations\StoreUnitRequest;
use App\Http\Requests\Locations\UpdateLocationRequest;
use App\Http\Requests\Locations\UpdateUnitRequest;
use App\Http\Requests\Units\ImportUnitsRequest;
use App\Models\Category;
use App\Models\EsgMeasurement;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Support\EntityDetailNavigation;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Tenancy;
use App\Support\Translation\LocaleSupport;
use App\Models\QrLinkPhoto;
use App\Models\Unit;
use App\Models\UnitBulkBatch;
use App\Models\UnitCheckList;
use App\Models\UnitMeasureField;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use App\Support\Units\ImportBatchRegistry;
use App\Support\Units\UnitBulkBatchRegistry;
use App\Support\Units\UnitDeletionGuard;
use App\Support\Validation\TextDescriptionLimits;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Validation\ValidationException;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Show extends Component
{
    use WithFileUploads;
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public Location $location;

    public bool $showLocationModal = false;

    public bool $showUnitModal = false;

    public bool $showBulkModal = false;

    public bool $showCsvImportModal = false;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $csvImportFile = null;

    /** @var list<string> */
    public array $csvImportErrors = [];

    public ?string $unitsImportNotice = null;

    public string $unitsImportNoticeType = 'success';

    public bool $showQrPackModal = false;

    public bool $qrPackGenerateDynamic = false;

    public string $qrPackDynamicCount = '15';

    public bool $showUnitQrPackModal = false;

    public ?int $unitQrPackUnitId = null;

    public ?int $editingUnitId = null;

    public string $locationFormName = '';

    public string $locationFormStreet = '';

    public string $locationFormHouseNumber = '';

    public string $locationFormPostalCode = '';

    public string $locationFormCity = '';

    public string $locationFormCountryCode = 'BE';

    public string $locationFormNotes = '';

    public string $unitName = '';

    public string $unitDescription = '';

    public ?int $unitCategoryId = null;

    public ?int $unitCheckListId = null;

    public string $unitExternalId = '';

    public bool $unitPublicReportsEnabled = true;

    public bool $unitAllowReservations = false;

    public bool $unitAllowUnitChecks = false;

    public bool $unitAllowUnitMeasurements = false;

    /** @var array<int, int> */
    public array $unitMeasureFieldIds = [];

    public bool $unitRequireReporterContact = false;

    public bool $unitRequireReporterEmailVerification = false;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $unitPhotos = [];

    public ?int $focusUnitId = null;

    public string $unitCategoryFilter = '';

    #[Url(as: 'unit')]
    public string $unitSearch = '';

    public string $previewLocale = '';

    public string $unitTranslationName = '';

    public string $unitTranslationDescription = '';

    public string $locationPreviewLocale = '';

    public string $locationTranslationName = '';

    public function updatedUnitCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUnitSearch(): void
    {
        $this->resetPage();
    }

    /** @var list<array{start: string, count: int|string, padding: string, prefix: string, suffix: string}> */
    public array $bulkRanges = [];

    public ?int $bulkCategoryId = null;

    public function mount(Location $location): void
    {
        $this->authorize('view', $location);
        $this->location = $location;
        $this->previewLocale = LocaleSupport::normalize(app()->getLocale());

        if (request()->query('edit') === 'location') {
            $this->openEditLocation();
        }

        $editUnitId = (int) request()->query('edit_unit', 0);
        if ($editUnitId > 0) {
            $this->openEditUnit($editUnitId);
        }

        $focusUnitId = (int) request()->query('unit_id', 0);
        if ($focusUnitId > 0) {
            $this->focusUnitId = $focusUnitId;
            $focusUnit = Unit::query()
                ->where('location_id', $location->id)
                ->find($focusUnitId);
            if ($focusUnit !== null) {
                $this->unitSearch = $focusUnit->localizedName();
            }
        }
    }

    public function openEditLocation(): void
    {
        $this->authorize('update', $this->location);
        $this->location->refresh();
        $this->location->loadMissing('translations');
        $this->fillLocationFormFromModel();
        $this->locationPreviewLocale = $this->defaultTranslationLocaleForLocation($this->location);
        $this->hydrateLocationTranslationInput($this->location);
        $this->resetErrorBag();
        $this->showLocationModal = true;
    }

    public function closeLocationModal(): void
    {
        $this->showLocationModal = false;
        $this->location->refresh();
        $this->resetLocationForm();
        $this->resetErrorBag();
    }

    public function updatedLocationPreviewLocale(): void
    {
        $this->location->loadMissing('translations');
        $this->hydrateLocationTranslationInput($this->location);
    }

    public function saveLocationTranslationOverride(ImportLocationTranslationsAction $importLocationTranslations): void
    {
        $this->authorize('update', $this->location);

        if (! $this->location->is_active) {
            $this->addError('locationTranslationName', __('locations.errors.translation_requires_active'));

            return;
        }

        $validated = $this->validate([
            'locationTranslationName' => ['required', 'string', 'max:255'],
        ]);

        $locale = LocaleSupport::normalize($this->locationPreviewLocale);
        if ($locale === $this->location->normalizedOriginalLanguage()) {
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
                    'location_id' => $this->location->id,
                    'locale' => $locale,
                    'name' => $name,
                ],
            ], (int) auth()->id());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                if (is_array($messages)) {
                    foreach ($messages as $message) {
                        $this->addError('locationTranslationName', (string) $message);
                    }
                }
            }

            return;
        }

        $this->location = $this->location->fresh('translations');
        $this->hydrateLocationTranslationInput($this->location);
        session()->flash('success', __('locations.flash.translation_saved'));
    }

    public function saveLocation(UpdateLocationAction $updateLocation): void
    {
        $this->authorize('update', $this->location);
        $validated = UpdateLocationRequest::validatePayload($this->locationFormPayload());
        $this->location = $updateLocation->handle($this->location, $validated, (int) auth()->id());
        $this->showLocationModal = false;
        $this->resetLocationForm();
        session()->flash('success', __('locations.flash.updated'));
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
        ];
    }

    private function fillLocationFormFromModel(): void
    {
        $this->locationFormName = (string) $this->location->name;
        $this->locationFormStreet = (string) ($this->location->street ?? $this->location->address ?? '');
        $this->locationFormHouseNumber = (string) ($this->location->house_number ?? '');
        $this->locationFormPostalCode = (string) ($this->location->postal_code ?? '');
        $this->locationFormCity = (string) ($this->location->city ?? '');
        $this->locationFormCountryCode = (string) ($this->location->country_code ?? 'BE');
        $this->locationFormNotes = (string) ($this->location->notes ?? '');
    }

    private function resetLocationForm(): void
    {
        $this->reset([
            'locationFormName',
            'locationFormStreet',
            'locationFormHouseNumber',
            'locationFormPostalCode',
            'locationFormCity',
            'locationFormNotes',
            'locationPreviewLocale',
            'locationTranslationName',
        ]);
        $this->locationFormCountryCode = 'BE';
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

    public function deactivateLocation(DeactivateLocationAction $deactivateLocation): void
    {
        $this->authorize('deactivate', $this->location);
        $deactivateLocation->handle($this->location, (int) auth()->id());
        session()->flash('success', __('locations.flash.deactivated'));

        $this->redirect(route('locations.index'), navigate: true);
    }

    public function openCreateUnit(): void
    {
        $this->authorize('create', Unit::class);
        $this->editingUnitId = null;
        $this->unitName = '';
        $this->unitDescription = '';
        $this->unitCategoryId = null;
        $this->unitCheckListId = null;
        $this->unitExternalId = '';
        $this->unitPublicReportsEnabled = true;
        $this->unitAllowReservations = false;
        $this->unitAllowUnitChecks = false;
        $this->unitAllowUnitMeasurements = false;
        $this->unitMeasureFieldIds = [];
        $this->unitRequireReporterContact = false;
        $this->unitRequireReporterEmailVerification = false;
        $this->resetErrorBag();
        $this->showUnitModal = true;
    }

    public function openEditUnit(int $unitId): void
    {
        $unit = Unit::where('location_id', $this->location->id)
            ->with(['qrCodes' => fn ($q) => $q->where('status', \App\Enums\QrCodeStatus::Active)])
            ->findOrFail($unitId);
        $this->authorize('update', $unit);
        $this->editingUnitId = $unit->id;
        $this->unitName = $unit->name;
        $this->unitDescription = $unit->description ?? '';
        $this->unitCategoryId = $unit->category_id;
        $this->unitCheckListId = $unit->unit_check_list_id;
        $this->unitExternalId = (string) ($unit->external_id ?? '');
        $this->unitPublicReportsEnabled = (bool) $unit->public_reports_enabled;
        $this->unitAllowReservations = (bool) $unit->allow_reservations;
        $this->unitAllowUnitChecks = (bool) $unit->allow_unit_checks;
        $this->unitAllowUnitMeasurements = (bool) $unit->allow_unit_measurements;
        $this->unitMeasureFieldIds = $unit->measureFields()
            ->pluck('unit_measure_fields.id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->unitRequireReporterContact = (bool) $unit->require_reporter_contact;
        $this->unitRequireReporterEmailVerification = (bool) $unit->require_reporter_email_verification;
        $this->unitPhotos = [];
        $this->previewLocale = $this->defaultTranslationLocaleFor($unit);
        $this->hydrateUnitTranslationInputs($unit->fresh('translations'));

        $this->resetErrorBag();
        $this->showUnitModal = true;
        $this->dispatch('wp-prepare-photo-inputs');
    }

    public function closeUnitModal(): void
    {
        $this->showUnitModal = false;
        $this->editingUnitId = null;
        $this->unitName = '';
        $this->unitDescription = '';
        $this->unitCategoryId = null;
        $this->unitCheckListId = null;
        $this->unitExternalId = '';
        $this->unitPublicReportsEnabled = true;
        $this->unitAllowReservations = false;
        $this->unitAllowUnitChecks = false;
        $this->unitAllowUnitMeasurements = false;
        $this->unitMeasureFieldIds = [];
        $this->unitRequireReporterContact = false;
        $this->unitRequireReporterEmailVerification = false;
        $this->unitPhotos = [];
        $this->unitTranslationName = '';
        $this->unitTranslationDescription = '';
        $this->resetErrorBag();
        $this->dispatch('wp-clear-photo-previews');
    }

    public function updatedPreviewLocale(): void
    {
        if ($this->editingUnitId === null) {
            $this->unitTranslationName = '';
            $this->unitTranslationDescription = '';

            return;
        }

        $unit = Unit::query()
            ->where('location_id', $this->location->id)
            ->with('translations')
            ->find($this->editingUnitId);

        $this->hydrateUnitTranslationInputs($unit);
    }

    public function openQrPackModal(): void
    {
        $this->showQrPackModal = true;
    }

    public function closeQrPackModal(): void
    {
        $this->showQrPackModal = false;
    }

    public function openUnitQrPackModal(int $unitId): void
    {
        $unit = Unit::query()
            ->where('location_id', $this->location->id)
            ->findOrFail($unitId);

        $this->authorize('view', $unit);

        $this->unitQrPackUnitId = $unit->id;
        $this->showUnitQrPackModal = true;
    }

    public function closeUnitQrPackModal(): void
    {
        $this->showUnitQrPackModal = false;
        $this->unitQrPackUnitId = null;
    }

    public function removeUnitPhoto(int $photoId, DeleteQrLinkPhotoAction $deletePhoto): void
    {
        $photo = QrLinkPhoto::find($photoId);
        if ($photo === null) {
            return;
        }

        $this->authorize('update', Unit::findOrFail($photo->unit_id));
        $deletePhoto->handle($photo, (int) auth()->id());
    }

    public function removeUnitTempPhoto(int $index): void
    {
        if (isset($this->unitPhotos[$index])) {
            array_splice($this->unitPhotos, $index, 1);
        }
    }

    public function saveUnit(CreateUnitAction $createUnit, UpdateUnitAction $updateUnit): void
    {
        $rules = $this->editingUnitId === null
            ? StoreUnitRequest::ruleSet($this->location->id, null, (int) auth()->user()->tenant_id)
            : UpdateUnitRequest::ruleSetFor($this->location->id, $this->editingUnitId, (int) auth()->user()->tenant_id);

        $photoSlotsLeft = 4;
        if ($this->editingUnitId !== null) {
            $photoSlotsLeft = max(0, 4 - (int) QrLinkPhoto::query()->where('unit_id', $this->editingUnitId)->count());
        }

        $validated = $this->validate([
            'unitName' => $rules['name'],
            'unitDescription' => $rules['description'],
            'unitCategoryId' => $rules['category_id'],
            'unitCheckListId' => $rules['unit_check_list_id'],
            'unitExternalId' => $rules['external_id'],
            'unitPublicReportsEnabled' => $rules['public_reports_enabled'],
            'unitAllowReservations' => $rules['allow_reservations'],
            'unitAllowUnitChecks' => $rules['allow_unit_checks'],
            'unitAllowUnitMeasurements' => $rules['allow_unit_measurements'],
            'unitMeasureFieldIds' => $rules['measure_field_ids'],
            'unitMeasureFieldIds.*' => $rules['measure_field_ids.*'],
            'unitRequireReporterContact' => $rules['require_reporter_contact'],
            'unitRequireReporterEmailVerification' => $rules['require_reporter_email_verification'],
            'unitPhotos' => ['nullable', 'array', 'max:'.$photoSlotsLeft],
            'unitPhotos.*' => ['image', 'max:10240'],
        ], [
            'unitName.required' => __('locations.units.errors.name_required'),
            'unitName.unique' => __('locations.units.errors.duplicate_name'),
            'unitCategoryId.exists' => __('locations.units.errors.invalid_category'),
            'unitCheckListId.exists' => __('locations.units.errors.invalid_check_list'),
            'unitExternalId.unique' => __('locations.units.errors.external_id_taken'),
            'unitPhotos.max' => __('portal.report.errors.photos_max'),
            'unitPhotos.*.image' => __('portal.report.errors.photos_image'),
            'unitPhotos.*.max' => __('portal.report.errors.photos_size'),
        ]);

        $payload = [
            'name' => $validated['unitName'],
            'description' => $validated['unitDescription'] ?? null,
            'category_id' => $validated['unitCategoryId'] ?? null,
            'unit_check_list_id' => $validated['unitCheckListId'] ?? null,
            'external_id' => $validated['unitExternalId'] ?? null,
            'public_reports_enabled' => (bool) $validated['unitPublicReportsEnabled'],
            'allow_reservations' => (bool) $validated['unitAllowReservations'],
            'allow_unit_checks' => (bool) $validated['unitAllowUnitChecks'],
            'allow_unit_measurements' => (bool) $validated['unitAllowUnitMeasurements'],
            'measure_field_ids' => array_map('intval', $validated['unitMeasureFieldIds'] ?? []),
            'require_reporter_contact' => (bool) $validated['unitRequireReporterContact'],
            'require_reporter_email_verification' => (bool) $validated['unitRequireReporterEmailVerification'],
            'original_language' => auth()->user()->locale ?? null,
        ];

        if ($this->editingUnitId === null) {
            $this->authorize('create', Unit::class);
            try {
                $unit = $createUnit->handle($this->location, $payload, (int) auth()->user()->tenant_id, (int) auth()->id());
            } catch (InvalidArgumentException $e) {
                if ($e->getMessage() === 'unit_limit_exceeded') {
                    $this->addError('unitName', __('locations.errors.unit_limit'));

                    return;
                }

                throw $e;
            }

            session()->flash('success', __('locations.units.flash.created'));
        } else {
            $unit = Unit::findOrFail($this->editingUnitId);
            $this->authorize('update', $unit);
            $updateUnit->handle($unit, $payload, (int) auth()->id(), $this->unitPhotos);

            session()->flash('success', __('locations.units.flash.updated'));
        }

        $this->reset('unitPhotos');
        $this->dispatch('wp-clear-photo-previews');
        $this->showUnitModal = false;
        $this->unitCategoryId = null;
        $this->unitCheckListId = null;
        $this->location->refresh();
    }

    public function saveUnitTranslationOverride(ImportUnitTranslationsAction $importUnitTranslations): void
    {
        if ($this->editingUnitId === null) {
            return;
        }

        $unit = Unit::query()
            ->where('location_id', $this->location->id)
            ->with('translations')
            ->findOrFail($this->editingUnitId);

        $this->authorize('update', $unit);

        $validated = $this->validate([
            'unitTranslationName' => ['nullable', 'string', 'max:255'],
            'unitTranslationDescription' => ['nullable', 'string', 'max:'.TextDescriptionLimits::TRANSLATION_MAX],
        ]);

        $locale = LocaleSupport::normalize($this->previewLocale);
        if ($locale === $unit->normalizedOriginalLanguage()) {
            $this->addError('unitTranslationDescription', __('issues.errors.translation_same_as_source'));

            return;
        }

        $name = trim((string) ($validated['unitTranslationName'] ?? ''));
        $description = trim((string) ($validated['unitTranslationDescription'] ?? ''));

        if ($name === '' && $description === '') {
            $this->addError('unitTranslationDescription', __('issues.errors.translation_import_invalid'));

            return;
        }

        try {
            $importUnitTranslations->handle([
                [
                    'unit_id' => $unit->id,
                    'locale' => $locale,
                    'name' => $name,
                    'description' => $description,
                ],
            ], (int) auth()->id());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                if (is_array($messages)) {
                    foreach ($messages as $message) {
                        $this->addError('unitTranslationDescription', (string) $message);
                    }
                }
            }

            return;
        }

        $this->hydrateUnitTranslationInputs($unit->fresh('translations'));
        session()->flash('success', __('locations.units.flash.translation_saved'));
    }

    private function hydrateUnitTranslationInputs(?Unit $unit): void
    {
        if ($unit === null) {
            $this->unitTranslationName = '';
            $this->unitTranslationDescription = '';

            return;
        }

        $locale = LocaleSupport::normalize($this->previewLocale);
        if ($locale === $unit->normalizedOriginalLanguage()) {
            $locale = $this->defaultTranslationLocaleFor($unit);
            $this->previewLocale = $locale;
        }

        $translation = $unit->translations
            ->first(fn ($row) => $row->locale === $locale);

        $this->unitTranslationName = (string) ($translation?->name ?? '');
        $this->unitTranslationDescription = (string) ($translation?->description ?? '');
    }

    private function defaultTranslationLocaleFor(Unit $unit): string
    {
        $targets = LocaleSupport::targetLocalesForSource($unit->normalizedOriginalLanguage());
        $preferred = LocaleSupport::normalize(auth()->user()?->locale ?? app()->getLocale());

        if (in_array($preferred, $targets, true)) {
            return $preferred;
        }

        return $targets[0] ?? $preferred;
    }

    public function deactivateUnit(int $unitId, DeactivateUnitAction $deactivateUnit): void
    {
        $unit = Unit::where('location_id', $this->location->id)->findOrFail($unitId);
        $this->authorize('deactivate', $unit);
        $deactivateUnit->handle($unit, (int) auth()->id());
        session()->flash('success', __('locations.units.flash.deactivated'));
        $this->location->refresh();
    }

    public function activateUnit(int $unitId, ActivateUnitAction $activateUnit): void
    {
        $unit = Unit::where('location_id', $this->location->id)->findOrFail($unitId);
        $this->authorize('activate', $unit);
        $activateUnit->handle($unit, (int) auth()->id());
        session()->flash('success', __('locations.units.flash.activated'));
        $this->location->refresh();
    }

    public function deleteUnit(int $unitId, DeleteUnitAction $deleteUnit): void
    {
        $unit = Unit::where('location_id', $this->location->id)->findOrFail($unitId);
        $this->authorize('delete', $unit);

        try {
            $deleteUnit->handle($unit, (int) auth()->id());
            session()->flash('success', __('locations.units.flash.deleted'));
        } catch (InvalidArgumentException $e) {
            session()->flash('error', __(UnitDeletionGuard::blockMessageKey($e->getMessage())));
        }

        $this->location->refresh();
    }

    public function openBulkModal(): void
    {
        $this->authorize('create', Unit::class);
        $this->bulkRanges = [$this->emptyBulkRange()];
        $this->bulkCategoryId = null;
        $this->resetErrorBag();
        $this->showBulkModal = true;
    }

    /**
     * @return array{start: string, count: int, padding: string, prefix: string, suffix: string}
     */
    private function emptyBulkRange(): array
    {
        return [
            'start' => '',
            'count' => 1,
            'padding' => '',
            'prefix' => '',
            'suffix' => '',
        ];
    }

    public function closeBulkModal(): void
    {
        $this->showBulkModal = false;
    }

    public function openCsvImportModal(): void
    {
        $this->authorize('create', Unit::class);
        abort_unless($this->locationTenant()?->hasCsvUnitsImport() ?? false, 403);

        $this->csvImportFile = null;
        $this->csvImportErrors = [];
        $this->showCsvImportModal = true;
    }

    public function closeCsvImportModal(): void
    {
        $this->showCsvImportModal = false;
        $this->csvImportFile = null;
        $this->csvImportErrors = [];
    }

    public function importUnitsCsv(ImportUnitsAction $importUnits): void
    {
        $this->authorize('create', Unit::class);
        abort_unless($this->locationTenant()?->hasCsvUnitsImport() ?? false, 403);

        if ($this->csvImportFile === null) {
            $this->csvImportErrors = [__('locations.units_csv.errors.file_required')];

            return;
        }

        $validator = Validator::make(
            ['file' => $this->csvImportFile],
            ImportUnitsRequest::getReusableRules(),
            ImportUnitsRequest::getReusableMessages()
        );

        if ($validator->fails()) {
            $this->csvImportErrors = $validator->errors()->all();

            return;
        }

        $result = $importUnits->handle(
            new ImportUnitsData(
                filePath: $this->csvImportFile->getRealPath(),
                originalName: $this->csvImportFile->getClientOriginalName(),
                locationId: (int) $this->location->id,
            ),
            (int) Tenancy::id(),
            (int) auth()->id(),
        );

        if ($result['success']) {
            session()->flash('success', __('locations.flash.imported', ['count' => $result['count']]));
            $this->closeCsvImportModal();
            $this->location->refresh();

            return;
        }

        $this->csvImportErrors = $result['errors'] ?? [__('locations.units_csv.errors.failed')];
    }

    public function downloadLocationUnitsSampleCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('create', Unit::class);
        abort_unless($this->locationTenant()?->hasCsvUnitsImport() ?? false, 403);

        $headers = ImportUnitsAction::allHeaders();
        $sampleRow = [
            __('locations.import_sample.sample_unit_name'),
            __('locations.import_sample.sample_description'),
            __('locations.import_sample.sample_category_name'),
            __('locations.import_sample.sample_external_id'),
            '',
            '',
            '',
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
        }, 'units-location-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadLocationUnitsSampleXlsx(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('create', Unit::class);
        abort_unless($this->locationTenant()?->hasCsvUnitsImport() ?? false, 403);

        $rows = [
            ImportUnitsAction::allHeaders(),
            [
                __('locations.import_sample.sample_unit_name'),
                __('locations.import_sample.sample_description'),
                __('locations.import_sample.sample_category_name'),
                __('locations.import_sample.sample_external_id'),
                '',
                '',
                '',
                '',
                '',
                '',
            ],
        ];

        return response()->streamDownload(function () use ($rows) {
            $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'units-location-sample-'.uniqid('', true).'.xlsx';
            try {
                \App\Support\Import\MinimalXlsxWriter::write($tempPath, $rows);
                readfile($tempPath);
            } finally {
                @unlink($tempPath);
            }
        }, 'units-location-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function deleteImportBatch(string $batchId, DeleteImportBatchAction $deleteBatch): void
    {
        $this->authorize('create', Unit::class);

        $tenantId = (int) Tenancy::id();
        $summary = ImportBatchRegistry::summary($tenantId, $batchId, (int) $this->location->id);

        if (! $summary['can_delete']) {
            $this->unitsImportNotice = __('locations.import_history.nothing_deletable');
            $this->unitsImportNoticeType = 'error';

            return;
        }

        $result = $deleteBatch->handle(
            new DeleteImportBatchData(importBatchId: $batchId),
            $tenantId,
            (int) auth()->id(),
        );

        if ($result['success']) {
            $categoriesDeleted = (int) ($result['deleted_category_count'] ?? 0);

            if ($result['preserved_count'] > 0) {
                $this->unitsImportNotice = $categoriesDeleted > 0
                    ? __('locations.import_history.partially_deleted_with_cleanup', [
                        'deleted' => $result['deleted_count'],
                        'preserved' => $result['preserved_count'],
                        'locations' => 0,
                        'categories' => $categoriesDeleted,
                    ])
                    : __('locations.import_history.partially_deleted', [
                        'deleted' => $result['deleted_count'],
                        'preserved' => $result['preserved_count'],
                    ]);
            } else {
                $this->unitsImportNotice = $categoriesDeleted > 0
                    ? __('locations.import_history.fully_deleted_with_cleanup', [
                        'count' => $result['deleted_count'],
                        'locations' => 0,
                        'categories' => $categoriesDeleted,
                    ])
                    : __('locations.import_history.fully_deleted', [
                        'count' => $result['deleted_count'],
                    ]);
            }

            $this->unitsImportNoticeType = 'success';
            $this->location->refresh();
        } else {
            $this->unitsImportNotice = $result['errors'][0] ?? __('locations.import_history.delete_failed');
            $this->unitsImportNoticeType = 'error';
        }
    }

    private function locationTenant(): ?Tenant
    {
        return Tenant::query()->find($this->location->tenant_id);
    }

    public function getEditingUnitProperty(): ?Unit
    {
        if ($this->editingUnitId === null) {
            return null;
        }

        return Unit::with(['qrLinkPhotos', 'latestGpsReport', 'translations'])->find($this->editingUnitId);
    }

    public function createBulk(BulkCreateUnitsAction $bulkCreate): void
    {
        $this->authorize('create', Unit::class);

        $this->bulkRanges = array_values(array_map(function (array $range): array {
            $padding = $range['padding'] ?? '';

            return [
                'start' => trim((string) ($range['start'] ?? '')),
                'count' => (int) ($range['count'] ?? 0),
                'padding' => ($padding === '' || $padding === null) ? null : (int) $padding,
                'prefix' => (string) ($range['prefix'] ?? ''),
                'suffix' => (string) ($range['suffix'] ?? ''),
            ];
        }, $this->bulkRanges));

        $validated = $this->validate([
            ...BulkCreateUnitsRequest::livewireRuleSet(),
            'bulkCategoryId' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $consistency = Validator::make([], []);
        BulkCreateUnitsRequest::assertRangesConsistent(
            $consistency,
            $validated['bulkRanges'],
            'bulkRanges',
        );
        if ($consistency->fails()) {
            foreach ($consistency->errors()->messages() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }

            return;
        }

        try {
            $result = $bulkCreate->handle($this->location, [
                'ranges' => $validated['bulkRanges'],
                'category_id' => $validated['bulkCategoryId'] ?? null,
            ], (int) auth()->user()->tenant_id, (int) auth()->id());

            session()->flash('success', __('locations.bulk.created', ['count' => $result['created']]));
            $this->showBulkModal = false;
            $this->location->refresh();
        } catch (InvalidArgumentException $e) {
            $key = match ($e->getMessage()) {
                'duplicates' => 'locations.bulk.errors.duplicates',
                'names_exist' => 'locations.bulk.errors.names_exist',
                'too_many' => 'locations.bulk.errors.too_many',
                'unit_limit_exceeded' => 'locations.errors.unit_limit',
                default => 'locations.bulk.errors.invalid',
            };
            $this->addError('bulkRanges', __($key));
        }
    }

    public function deleteBulkBatch(int $batchId, DeleteUnitBulkBatchAction $deleteBatch): void
    {
        $batch = UnitBulkBatch::where('location_id', $this->location->id)->findOrFail($batchId);
        $this->authorize('create', Unit::class);

        $result = $deleteBatch->handle($batch, (int) auth()->id());

        if ($result['deleted'] === 0) {
            session()->flash('error', __('locations.bulk.batch_nothing_deletable'));
        } else {
            session()->flash('success', __('locations.bulk.batch_deleted', [
                'deleted' => $result['deleted'],
                'skipped' => $result['skipped'],
            ]));
        }

        $this->location->refresh();
    }

    public function render()
    {
        $categoriesEnabled = Schema::hasTable('categories');

        $units = Unit::query()
            ->where('location_id', $this->location->id)
            ->with(['qrCodes' => function ($q) {
                $q->where('status', \App\Enums\QrCodeStatus::Active);
            }])
            ->when($categoriesEnabled, fn ($q) => $q->with([
                'category.translations',
                'category.teams.translations',
            ]))
            ->with('translations')
            ->withExists('gpsReports')
            ->withCount('issues')
            ->when($categoriesEnabled && $this->unitCategoryFilter !== '', fn ($q) => $q->where('category_id', (int) $this->unitCategoryFilter))
            ->when(trim($this->unitSearch) !== '', function ($q) {
                $term = '%'.trim($this->unitSearch).'%';
                $q->where(function ($unitQuery) use ($term) {
                    $unitQuery->where('name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(20);

        $bulkSummaries = UnitBulkBatchRegistry::recentBatchesForLocation($this->location)
            ->map(fn (UnitBulkBatch $batch) => array_merge(
                ['batch' => $batch],
                UnitBulkBatchRegistry::summary($batch),
            ));

        $tenantId = (int) $this->location->tenant_id;
        $unitImportBatches = ImportBatchRegistry::recentBatchesForLocation($tenantId, (int) $this->location->id)
            ->map(fn (array $batch) => array_merge(
                $batch,
                ImportBatchRegistry::summary($tenantId, $batch['batch_id'], (int) $this->location->id),
            ));

        $teams = InternalTeam::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'original_language']);

        $categories = $categoriesEnabled
            ? Category::query()->with('translations')->orderBy('name')->get(['id', 'name', 'original_language'])
            : collect();

        $categoryTeamIds = [];
        if ($this->unitCategoryId !== null) {
            $categoryTeamIds = Category::query()
                ->whereKey($this->unitCategoryId)
                ->first()
                ?->teams()
                ->pluck('internal_teams.id')
                ->map(fn ($id) => (int) $id)
                ->all() ?? [];
        }

        $unitCheckLists = UnitCheckList::query()
            ->where('is_active', true)
            ->where(function ($query) use ($categoryTeamIds): void {
                $query->whereNull('internal_team_id');
                if ($categoryTeamIds !== []) {
                    $query->orWhereIn('internal_team_id', $categoryTeamIds);
                }
            })
            ->with('translations')
            ->orderBy('name')
            ->get(['id', 'name', 'original_language']);

        // Keep currently attached list selectable while editing (inactive or out of filter).
        if ($this->unitCheckListId !== null && ! $unitCheckLists->contains('id', $this->unitCheckListId)) {
            $savedListId = null;
            if ($this->editingUnitId !== null) {
                $savedListId = Unit::query()
                    ->where('location_id', $this->location->id)
                    ->whereKey($this->editingUnitId)
                    ->value('unit_check_list_id');
            }

            if ($savedListId !== null && (int) $savedListId === (int) $this->unitCheckListId) {
                $currentList = UnitCheckList::query()->with('translations')->find($this->unitCheckListId);
                if ($currentList !== null) {
                    $unitCheckLists = $unitCheckLists->prepend($currentList)->unique('id')->values();
                }
            } else {
                $this->unitCheckListId = null;
            }
        }

        $previewUnit = null;
        $descriptionLocales = config('locales.labels', []);

        if ($this->showUnitModal && $this->editingUnitId !== null) {
            $previewUnit = Unit::query()
                ->where('location_id', $this->location->id)
                ->with('translations')
                ->find($this->editingUnitId);

            if ($previewUnit !== null) {
                $sourceLocale = $previewUnit->normalizedOriginalLanguage();
                $descriptionLocales = array_filter(
                    $descriptionLocales,
                    fn (string $label, string $code): bool => $code !== $sourceLocale,
                    ARRAY_FILTER_USE_BOTH,
                );
            }
        }

        $locationTranslationLocales = config('locales.labels', []);
        if ($this->showLocationModal) {
            $sourceLocale = $this->location->normalizedOriginalLanguage();
            $locationTranslationLocales = array_filter(
                $locationTranslationLocales,
                fn (string $label, string $code): bool => $code !== $sourceLocale,
                ARRAY_FILTER_USE_BOTH,
            );
        }

        $tenant = Tenant::query()->whereKey($this->location->tenant_id)->first();

        return view('livewire.locations.show', [
            'units' => $units,
            'bulkSummaries' => $bulkSummaries,
            'unitImportBatches' => $unitImportBatches,
            'teams' => $teams,
            'categories' => $categories,
            'unitCheckLists' => $unitCheckLists,
            'availableMeasureFields' => UnitMeasureField::query()->where('is_active', true)->orderBy('name')->get(),
            'hasApiAccess' => (bool) $tenant?->hasApiAccess(),
            'hasEsgModule' => (bool) ($tenant?->has_esg_module),
            'unitIdsWithEsgMeasurements' => EsgMeasurement::query()
                ->where('location_id', $this->location->id)
                ->whereNotNull('unit_id')
                ->distinct()
                ->pluck('unit_id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            'nav' => EntityDetailNavigation::forLocation($this->location),
            'qrPackTemplates' => QrStickerSheetTemplate::downloadCases(),
            'unitQrPackUnit' => $this->unitQrPackUnitId !== null
                ? Unit::query()
                    ->where('location_id', $this->location->id)
                    ->find($this->unitQrPackUnitId)
                : null,
            'unitQrPackTemplates' => QrStickerSheetTemplate::printableDownloadCases(),
            'previewUnit' => $previewUnit,
            'descriptionLocales' => $descriptionLocales,
            'locationTranslationLocales' => $locationTranslationLocales,
            'canImportUnitsCsv' => $this->locationTenant()?->hasCsvUnitsImport() ?? false,
        ]);
    }

}
