<?php

namespace App\Livewire\Customers;

use App\Actions\Customers\CreateCustomerAction;
use App\Actions\Customers\DeleteCustomerImportBatchAction;
use App\Actions\Customers\ImportCustomersAction;
use App\Actions\Customers\SuggestCustomerNameMatchesAction;
use App\Actions\Customers\UpdateCustomerAction;
use App\Actions\Locations\ActivateLocationAction;
use App\Actions\Locations\CreateLocationAction;
use App\Actions\Locations\DeactivateLocationAction;
use App\Actions\Locations\UpdateLocationAction;
use App\Data\Customers\DeleteCustomerImportBatchData;
use App\Data\Customers\ImportCustomersData;
use App\Http\Requests\Customers\ImportCustomersRequest;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Http\Requests\Locations\UpdateLocationRequest;
use App\Livewire\Concerns\AppliesGpsCoordinatePair;
use App\Livewire\Concerns\AppliesPastedAddress;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Tenant;
use App\Support\Customers\CustomerImportBatchRegistry;
use App\Support\Import\MinimalXlsxWriter;
use App\Support\Platform\SupportTenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Klanten — CRM-laag boven werkadressen (Locations). Kernscherm voor
 * Checkmate-tenants; ook voor Facility-tenants beschikbaar.
 */
#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Index extends Component
{
    use AppliesGpsCoordinatePair;
    use AppliesPastedAddress;
    use AuthorizesRequests;
    use WithFileUploads;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showInactive = false;

    public bool $showModal = false;

    public ?int $editingCustomerId = null;

    public string $customerFormName = '';

    public string $customerFormContactName = '';

    public string $customerFormEmail = '';

    public string $customerFormPhone = '';

    /** @var list<array{id: int, name: string}> */
    public array $customerNameMatches = [];

    /** @var array<int, bool> */
    public array $expandedCustomerIds = [];

    public bool $showCustomersCsvImportModal = false;

    /** @var TemporaryUploadedFile|null */
    public $customersCsvImportFile = null;

    /** @var list<string> */
    public array $customersCsvImportErrors = [];

    public ?string $customersImportNotice = null;

    public string $customersImportNoticeType = 'success';

    public bool $showLocationModal = false;

    public ?int $locationCustomerId = null;

    public ?int $editingLocationId = null;

    public string $locationFormName = '';

    public string $locationFormStreet = '';

    public string $locationFormHouseNumber = '';

    public string $locationFormPostalCode = '';

    public string $locationFormCity = '';

    public string $locationFormCountryCode = 'BE';

    public string $locationFormDdt = '';

    public string $locationFormNotes = '';

    public string $locationFormLatitude = '';

    public string $locationFormLongitude = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Customer::class);

        if (request()->boolean('create') && auth()->user()->can('create', Customer::class)) {
            $this->openCreate();
        }
    }

    public function updatedCustomerFormName(SuggestCustomerNameMatchesAction $suggest): void
    {
        $tenant = $this->resolveTenant();
        $this->customerNameMatches = $tenant === null ? [] : $suggest
            ->handle((int) $tenant->id, $this->customerFormName, $this->editingCustomerId)
            ->map(fn (Customer $c) => ['id' => (int) $c->id, 'name' => (string) $c->name])
            ->all();
    }

    public function toggleExpanded(int $customerId): void
    {
        $this->expandedCustomerIds[$customerId] = ! ($this->expandedCustomerIds[$customerId] ?? false);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Customer::class);
        $this->resetCustomerForm();
        $this->showModal = true;
    }

    public function openEdit(int $customerId): void
    {
        $customer = Customer::findOrFail($customerId);
        $this->authorize('update', $customer);

        $this->editingCustomerId = $customer->id;
        $this->customerFormName = (string) $customer->name;
        $this->customerFormContactName = (string) ($customer->contact_name ?? '');
        $this->customerFormEmail = (string) ($customer->email ?? '');
        $this->customerFormPhone = (string) ($customer->phone ?? '');
        $this->customerNameMatches = [];
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetCustomerForm();
    }

    public function saveCustomer(CreateCustomerAction $create, UpdateCustomerAction $update): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $validated = $this->validate($this->customerRules(), $this->customerMessages());

        $data = [
            'name' => (string) $validated['customerFormName'],
            'contact_name' => $validated['customerFormContactName'] ?? null,
            'email' => $validated['customerFormEmail'] ?? null,
            'phone' => $validated['customerFormPhone'] ?? null,
        ];

        if ($this->editingCustomerId !== null) {
            $customer = Customer::findOrFail($this->editingCustomerId);
            $this->authorize('update', $customer);
            $update->handle($customer, $data, (int) auth()->id());
        } else {
            $this->authorize('create', Customer::class);
            try {
                $create->handle($tenant, $data, (int) auth()->id());
            } catch (InvalidArgumentException $e) {
                if ($e->getMessage() === 'customer_name_required') {
                    $this->addError('customerFormName', __('customers.errors.name_required'));

                    return;
                }

                throw $e;
            }
        }

        $this->closeModal();
        session()->flash('success', __('customers.saved'));
    }

    public function toggleCustomerActive(int $customerId, UpdateCustomerAction $update): void
    {
        $customer = Customer::findOrFail($customerId);
        $this->authorize('update', $customer);

        $update->handle($customer, ['is_active' => ! $customer->is_active], (int) auth()->id());
    }

    public function openLocationCreate(int $customerId): void
    {
        $customer = Customer::findOrFail($customerId);
        $this->authorize('update', $customer);

        $this->locationCustomerId = (int) $customer->id;
        $this->editingLocationId = null;
        $this->resetLocationForm();
        $this->expandedCustomerIds[(int) $customer->id] = true;
        $this->showLocationModal = true;
    }

    public function openLocationEdit(int $locationId): void
    {
        $location = Location::findOrFail($locationId);
        $this->authorize('update', $location);

        $this->editingLocationId = (int) $location->id;
        $this->locationCustomerId = $location->customer_id !== null ? (int) $location->customer_id : null;
        $this->locationFormName = (string) $location->name;
        $this->locationFormStreet = (string) ($location->street ?? $location->address ?? '');
        $this->locationFormHouseNumber = (string) ($location->house_number ?? '');
        $this->locationFormPostalCode = (string) ($location->postal_code ?? '');
        $this->locationFormCity = (string) ($location->city ?? '');
        $this->locationFormCountryCode = (string) ($location->country_code ?? 'BE');
        $this->locationFormDdt = (string) ($location->contractual_relationship_reference ?? '');
        $this->locationFormNotes = (string) ($location->notes ?? '');
        $this->locationFormLatitude = $location->latitude !== null ? (string) $location->latitude : '';
        $this->locationFormLongitude = $location->longitude !== null ? (string) $location->longitude : '';
        $this->resetErrorBag();
        $this->showLocationModal = true;
    }

    public function closeLocationModal(): void
    {
        $this->showLocationModal = false;
        $this->locationCustomerId = null;
        $this->editingLocationId = null;
        $this->resetLocationForm();
    }

    public function saveLocation(CreateLocationAction $create, UpdateLocationAction $update): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $payload = [
            'name' => $this->locationFormName,
            'street' => $this->locationFormStreet,
            'house_number' => $this->locationFormHouseNumber,
            'postal_code' => $this->locationFormPostalCode,
            'city' => $this->locationFormCity,
            'country_code' => $this->locationFormCountryCode,
            'notes' => $this->locationFormNotes,
            'contractual_relationship_reference' => $this->locationFormDdt,
            'latitude' => $this->locationFormLatitude !== '' ? $this->locationFormLatitude : null,
            'longitude' => $this->locationFormLongitude !== '' ? $this->locationFormLongitude : null,
        ];

        if ($this->editingLocationId !== null) {
            $location = Location::findOrFail($this->editingLocationId);
            $this->authorize('update', $location);
            $validated = UpdateLocationRequest::validatePayload($payload);
            $update->handle($location, $validated, (int) auth()->id());

            $this->expandedCustomerIds[(int) $location->customer_id] = true;
            $this->closeLocationModal();
            session()->flash('success', __('customers.location_saved'));

            return;
        }

        $customer = $this->locationCustomerId !== null ? Customer::find($this->locationCustomerId) : null;
        if (! $customer instanceof Customer) {
            return;
        }
        $this->authorize('update', $customer);

        $validated = StoreLocationRequest::validatePayload($payload);

        try {
            $create->handle([
                ...$validated,
                'country_code' => strtoupper((string) ($validated['country_code'] ?? 'BE')),
                'customer_id' => (int) $customer->id,
                'with_site_unit' => ! $tenant->checkmateMode(),
            ], (int) $tenant->id, (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            $key = match ($e->getMessage()) {
                'location_limit_exceeded' => 'locations.errors.location_limit',
                'unit_limit_exceeded' => 'locations.errors.unit_limit',
                default => null,
            };

            if ($key !== null) {
                $this->addError('locationFormName', __($key));

                return;
            }

            throw $e;
        }

        $this->expandedCustomerIds[(int) $customer->id] = true;
        $this->closeLocationModal();
        session()->flash('success', __('customers.location_saved'));
    }

    public function toggleLocationActive(int $locationId, ActivateLocationAction $activate, DeactivateLocationAction $deactivate): void
    {
        $location = Location::findOrFail($locationId);
        $this->authorize('update', $location);

        if ($location->is_active) {
            $deactivate->handle($location, (int) auth()->id());
        } else {
            $activate->handle($location, (int) auth()->id());
        }
    }

    public function openCustomersCsvImportModal(): void
    {
        $this->authorize('create', Customer::class);
        abort_unless($this->resolveTenant()?->hasCsvCustomersImport() ?? false, 403);

        $this->customersCsvImportFile = null;
        $this->customersCsvImportErrors = [];
        $this->showCustomersCsvImportModal = true;
    }

    public function closeCustomersCsvImportModal(): void
    {
        $this->showCustomersCsvImportModal = false;
        $this->customersCsvImportFile = null;
        $this->customersCsvImportErrors = [];
    }

    public function importCustomersCsv(ImportCustomersAction $importCustomers): void
    {
        $this->authorize('create', Customer::class);
        $tenant = $this->resolveTenant();
        abort_unless($tenant?->hasCsvCustomersImport() ?? false, 403);

        if ($this->customersCsvImportFile === null) {
            $this->customersCsvImportErrors = [__('customers.customers_csv.errors.file_required')];

            return;
        }

        $validator = Validator::make(
            ['file' => $this->customersCsvImportFile],
            ImportCustomersRequest::getReusableRules(),
            ImportCustomersRequest::getReusableMessages()
        );

        if ($validator->fails()) {
            $this->customersCsvImportErrors = $validator->errors()->all();

            return;
        }

        $result = $importCustomers->handle(
            new ImportCustomersData(
                filePath: $this->customersCsvImportFile->getRealPath(),
                originalName: $this->customersCsvImportFile->getClientOriginalName(),
            ),
            (int) $tenant->id,
            (int) auth()->id(),
        );

        if ($result['success']) {
            session()->flash('success', __('customers.imported', [
                'count' => $result['count'],
                'locations' => $result['locations_count'] ?? 0,
            ]));
            $this->closeCustomersCsvImportModal();

            return;
        }

        $this->customersCsvImportErrors = $result['errors'] ?? [__('customers.customers_csv.errors.failed')];
    }

    public function downloadCustomersSampleCsv(): StreamedResponse
    {
        $this->authorize('create', Customer::class);
        abort_unless($this->resolveTenant()?->hasCsvCustomersImport() ?? false, 403);

        $headers = ImportCustomersAction::allHeaders();
        $sampleRow = [
            __('customers.import_sample.sample_customer_name'),
            __('customers.import_sample.sample_contact_name'),
            __('customers.import_sample.sample_email'),
            __('customers.import_sample.sample_phone'),
            __('customers.import_sample.sample_location_name'),
            __('customers.import_sample.sample_street'),
            __('customers.import_sample.sample_house_number'),
            __('customers.import_sample.sample_postal_code'),
            __('customers.import_sample.sample_city'),
            'BE',
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
        }, 'customers-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadCustomersSampleXlsx(): StreamedResponse
    {
        $this->authorize('create', Customer::class);
        abort_unless($this->resolveTenant()?->hasCsvCustomersImport() ?? false, 403);

        $rows = [
            ImportCustomersAction::allHeaders(),
            [
                __('customers.import_sample.sample_customer_name'),
                __('customers.import_sample.sample_contact_name'),
                __('customers.import_sample.sample_email'),
                __('customers.import_sample.sample_phone'),
                __('customers.import_sample.sample_location_name'),
                __('customers.import_sample.sample_street'),
                __('customers.import_sample.sample_house_number'),
                __('customers.import_sample.sample_postal_code'),
                __('customers.import_sample.sample_city'),
                'BE',
                '',
                '',
                '',
            ],
        ];

        return response()->streamDownload(function () use ($rows) {
            $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'customers-sample-'.uniqid('', true).'.xlsx';
            try {
                MinimalXlsxWriter::write($tempPath, $rows);
                readfile($tempPath);
            } finally {
                @unlink($tempPath);
            }
        }, 'customers-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function deleteCustomerImportBatch(string $batchId, DeleteCustomerImportBatchAction $deleteBatch): void
    {
        $this->authorize('create', Customer::class);

        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $summary = CustomerImportBatchRegistry::summary((int) $tenant->id, $batchId);

        if (! $summary['can_delete']) {
            $this->customersImportNotice = __('customers.customers_import_history.nothing_deletable');
            $this->customersImportNoticeType = 'error';

            return;
        }

        $result = $deleteBatch->handle(
            new DeleteCustomerImportBatchData(importBatchId: $batchId),
            (int) $tenant->id,
            (int) auth()->id(),
        );

        if (! ($result['success'] ?? false)) {
            $this->customersImportNotice = $result['errors'][0]
                ?? __('customers.customers_import_history.delete_failed');
            $this->customersImportNoticeType = 'error';

            return;
        }

        $deletedCustomers = (int) ($result['deleted_customers'] ?? 0);
        $deletedLocations = (int) ($result['deleted_locations'] ?? 0);
        $preserved = (int) ($result['preserved_count'] ?? 0);

        if ($preserved > 0) {
            $this->customersImportNotice = __('customers.customers_import_history.partially_deleted', [
                'customers' => $deletedCustomers,
                'locations' => $deletedLocations,
                'preserved' => $preserved,
            ]);
        } else {
            $this->customersImportNotice = __('customers.customers_import_history.fully_deleted', [
                'customers' => $deletedCustomers,
                'locations' => $deletedLocations,
            ]);
        }
        $this->customersImportNoticeType = 'success';
    }

    public function render()
    {
        $tenant = $this->resolveTenant();

        $customers = $tenant instanceof Tenant
            ? Customer::query()
                ->where('tenant_id', (int) $tenant->id)
                ->when(! $this->showInactive, fn ($q) => $q->where('is_active', true))
                ->when(trim($this->search) !== '', function ($q) {
                    $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($this->search)).'%';
                    $q->where(fn ($qq) => $qq
                        ->where('name', 'like', $term)
                        ->orWhere('contact_name', 'like', $term)
                        ->orWhere('email', 'like', $term));
                })
                ->with(['locations' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name')
                ->limit(200)
                ->get()
            : collect();

        return view('livewire.customers.index', [
            'customers' => $customers,
            'tenant' => $tenant,
            'checkmateMode' => $tenant?->checkmateMode() ?? false,
            'locationDdtVisible' => $tenant !== null
                && ($tenant->presenceComplianceEnabled() || $tenant->presenceComplianceRequested() || $tenant->checkmateMode()),
            'canImportCustomersCsv' => $tenant?->hasCsvCustomersImport() ?? false,
            'customerImportBatches' => $tenant instanceof Tenant
                ? CustomerImportBatchRegistry::recentBatchesForTenant((int) $tenant->id)
                    ->map(fn (array $batch) => array_merge(
                        $batch,
                        CustomerImportBatchRegistry::summary((int) $tenant->id, $batch['batch_id']),
                    ))
                : collect(),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function customerRules(): array
    {
        return [
            'customerFormName' => ['required', 'string', 'max:255'],
            'customerFormContactName' => ['nullable', 'string', 'max:255'],
            'customerFormEmail' => ['nullable', 'email', 'max:255'],
            'customerFormPhone' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customerMessages(): array
    {
        return [
            'customerFormName.required' => __('customers.errors.name_required'),
            'customerFormEmail.email' => __('customers.errors.email_invalid'),
        ];
    }

    private function resetCustomerForm(): void
    {
        $this->editingCustomerId = null;
        $this->customerFormName = '';
        $this->customerFormContactName = '';
        $this->customerFormEmail = '';
        $this->customerFormPhone = '';
        $this->customerNameMatches = [];
        $this->resetErrorBag();
    }

    private function resetLocationForm(): void
    {
        $this->locationFormName = '';
        $this->locationFormStreet = '';
        $this->locationFormHouseNumber = '';
        $this->locationFormPostalCode = '';
        $this->locationFormCity = '';
        $this->locationFormCountryCode = 'BE';
        $this->locationFormDdt = '';
        $this->locationFormNotes = '';
        $this->locationFormLatitude = '';
        $this->locationFormLongitude = '';
        $this->resetErrorBag();
    }

    private function resolveTenant(): ?Tenant
    {
        if (auth()->user()?->is_superuser && SupportTenantContext::isActive()) {
            return Tenant::query()->find(SupportTenantContext::activeTenantId());
        }

        return auth()->user()?->tenant;
    }
}
