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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use App\Support\Units\ImportBatchRegistry;
use App\Support\Units\UnitBulkBatchRegistry;
use App\Support\Units\UnitBulkNaming;
use App\Support\Units\UnitDeletionGuard;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

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

    public bool $unitPublicReportsEnabled = true;

    public bool $unitAllowReservations = true;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $unitPhotos = [];

    public ?int $focusUnitId = null;

    public string $unitCategoryFilter = '';

    #[Url(as: 'unit')]
    public string $unitSearch = '';

    public string $previewLocale = '';

    public function updatedUnitCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUnitSearch(): void
    {
        $this->resetPage();
    }

    public string $bulkFloors = '1';

    public string $bulkRoomsPerFloor = '1';

    public string $bulkScheme = UnitBulkNaming::SCHEME_COMPACT_2;

    public string $bulkPrefix = '';

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
        $this->fillLocationFormFromModel();
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
        ]);
        $this->locationFormCountryCode = 'BE';
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
        $this->unitPublicReportsEnabled = true;
        $this->unitAllowReservations = true;
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
        $this->unitPublicReportsEnabled = (bool) $unit->public_reports_enabled;
        $this->unitAllowReservations = (bool) $unit->allow_reservations;
        $this->unitPhotos = [];
        $this->previewLocale = LocaleSupport::normalize(auth()->user()?->locale ?? app()->getLocale());

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
        $this->unitPublicReportsEnabled = true;
        $this->unitAllowReservations = true;
        $this->unitPhotos = [];
        $this->resetErrorBag();
        $this->dispatch('wp-clear-photo-previews');
    }

    public function openQrPackModal(): void
    {
        $this->showQrPackModal = true;
    }

    public function closeQrPackModal(): void
    {
        $this->showQrPackModal = false;
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

        $validated = $this->validate([
            'unitName' => $rules['name'],
            'unitDescription' => $rules['description'],
            'unitCategoryId' => $rules['category_id'],
            'unitPublicReportsEnabled' => $rules['public_reports_enabled'],
            'unitAllowReservations' => $rules['allow_reservations'],
            'unitPhotos' => ['nullable', 'array', 'max:4'],
            'unitPhotos.*' => ['image', 'max:10240'],
        ], [
            'unitName.required' => __('locations.units.errors.name_required'),
            'unitName.unique' => __('locations.units.errors.duplicate_name'),
            'unitCategoryId.exists' => __('locations.units.errors.invalid_category'),
            'unitPhotos.max' => __('portal.report.errors.photos_max'),
            'unitPhotos.*.image' => __('portal.report.errors.photos_image'),
            'unitPhotos.*.max' => __('portal.report.errors.photos_size'),
        ]);

        $payload = [
            'name' => $validated['unitName'],
            'description' => $validated['unitDescription'] ?? null,
            'category_id' => $validated['unitCategoryId'] ?? null,
            'public_reports_enabled' => (bool) $validated['unitPublicReportsEnabled'],
            'allow_reservations' => (bool) $validated['unitAllowReservations'],
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
        $this->location->refresh();
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
        $this->bulkFloors = '3';
        $this->bulkRoomsPerFloor = '1';
        $this->bulkScheme = UnitBulkNaming::SCHEME_COMPACT_2;
        $this->bulkPrefix = '';
        $this->bulkCategoryId = null;
        $this->showBulkModal = true;
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

        $headers = ['unit_name', 'description', 'category_name'];
        $sampleRow = [
            __('locations.import_sample.sample_unit_name'),
            __('locations.import_sample.sample_description'),
            __('locations.import_sample.sample_category_name'),
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
            ['unit_name', 'description', 'category_name'],
            [
                __('locations.import_sample.sample_unit_name'),
                __('locations.import_sample.sample_description'),
                __('locations.import_sample.sample_category_name'),
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

    /**
     * @return list<string>
     */
    public function bulkPreviewNames(): array
    {
        if (! $this->showBulkModal) {
            return [];
        }

        $floorCount = max(1, (int) trim($this->bulkFloors));
        $roomsPerFloor = max(1, (int) trim($this->bulkRoomsPerFloor));

        if (UnitBulkNaming::validateConfig($floorCount, $roomsPerFloor, $this->bulkScheme) !== null) {
            return [];
        }

        try {
            $names = UnitBulkNaming::generate($floorCount, $roomsPerFloor, $this->bulkScheme, trim($this->bulkPrefix));
        } catch (\InvalidArgumentException) {
            return [];
        }

        $existing = Unit::query()
            ->where('location_id', $this->location->id)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        return array_values(array_slice(array_diff($names, $existing), 0, 16));
    }

    public function getBulkRoomsMaxProperty(): int
    {
        return $this->bulkScheme === UnitBulkNaming::SCHEME_COMPACT_2 ? 9 : 99;
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

        $bulkRules = BulkCreateUnitsRequest::ruleSet();
        $validated = $this->validate([
            'bulkFloors' => $bulkRules['floors'],
            'bulkRoomsPerFloor' => $bulkRules['rooms_per_floor'],
            'bulkScheme' => $bulkRules['scheme'],
            'bulkPrefix' => $bulkRules['prefix'],
            'bulkCategoryId' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        try {
            $result = $bulkCreate->handle($this->location, [
                'floors' => (int) $validated['bulkFloors'],
                'rooms_per_floor' => (int) $validated['bulkRoomsPerFloor'],
                'scheme' => $validated['bulkScheme'],
                'prefix' => $validated['bulkPrefix'] ?? '',
                'category_id' => $validated['bulkCategoryId'] ?? null,
            ], (int) auth()->user()->tenant_id, (int) auth()->id());

            session()->flash('success', __('locations.bulk.created', ['count' => $result['created']]));
            $this->showBulkModal = false;
            $this->location->refresh();
        } catch (InvalidArgumentException $e) {
            $key = match ($e->getMessage()) {
                'scheme_rooms' => 'locations.bulk.errors.scheme_rooms',
                'scheme_floors' => 'locations.bulk.errors.scheme_floors',
                'scheme_range' => 'locations.bulk.errors.scheme_range',
                'names_exist' => 'locations.bulk.errors.names_exist',
                'too_many' => 'locations.bulk.errors.too_many',
                'unit_limit_exceeded' => 'locations.errors.unit_limit',
                default => 'locations.bulk.errors.invalid',
            };
            $this->addError('bulkFloors', __($key));
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
            ->when($categoriesEnabled, fn ($q) => $q->with('category:id,name'))
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
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $categories = $categoriesEnabled
            ? Category::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        $previewName = '';
        $previewNameMissing = false;
        $previewDescription = '';
        $previewDescriptionMissing = false;
        $previewUnit = null;

        if ($this->showUnitModal && $this->editingUnitId !== null) {
            $previewUnit = Unit::query()
                ->where('location_id', $this->location->id)
                ->with('translations')
                ->find($this->editingUnitId);

            if ($previewUnit !== null && $previewUnit->is_active) {
                $displayLocale = LocaleSupport::normalize($this->previewLocale);
                $previewName = $previewUnit->nameForDisplayLocale($displayLocale);
                $previewNameMissing = $previewUnit->nameMissingForDisplayLocale($displayLocale);
                $previewDescription = $previewUnit->descriptionForDisplayLocale($displayLocale);
                $previewDescriptionMissing = $previewUnit->descriptionMissingForDisplayLocale($displayLocale);
            }
        }

        return view('livewire.locations.show', [
            'units' => $units,
            'bulkSummaries' => $bulkSummaries,
            'unitImportBatches' => $unitImportBatches,
            'teams' => $teams,
            'categories' => $categories,
            'hasEsgModule' => (bool) Tenant::query()
                ->whereKey($this->location->tenant_id)
                ->value('has_esg_module'),
            'unitIdsWithEsgMeasurements' => EsgMeasurement::query()
                ->where('location_id', $this->location->id)
                ->whereNotNull('unit_id')
                ->distinct()
                ->pluck('unit_id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            'nav' => EntityDetailNavigation::forLocation($this->location),
            'bulkPreview' => $this->bulkPreviewNames(),
            'qrPackTemplates' => QrStickerSheetTemplate::cases(),
            'previewUnit' => $previewUnit,
            'previewName' => $previewName,
            'previewNameMissing' => $previewNameMissing,
            'previewDescription' => $previewDescription,
            'previewDescriptionMissing' => $previewDescriptionMissing,
            'descriptionLocales' => config('locales.labels', []),
            'canImportUnitsCsv' => $this->locationTenant()?->hasCsvUnitsImport() ?? false,
        ]);
    }

}
