<?php

namespace App\Livewire\Customers;

use App\Actions\Customers\CreateCustomerAction;
use App\Actions\Customers\SuggestCustomerNameMatchesAction;
use App\Actions\Customers\UpdateCustomerAction;
use App\Actions\Locations\ActivateLocationAction;
use App\Actions\Locations\CreateLocationAction;
use App\Actions\Locations\DeactivateLocationAction;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Tenant;
use App\Support\Platform\SupportTenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Klanten — CRM-laag boven werkadressen (Locations). Kernscherm voor
 * Checkmate-tenants; ook voor Facility-tenants beschikbaar.
 */
#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Index extends Component
{
    use AuthorizesRequests;

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

    public bool $showLocationModal = false;

    public ?int $locationCustomerId = null;

    public string $locationFormName = '';

    public string $locationFormStreet = '';

    public string $locationFormHouseNumber = '';

    public string $locationFormPostalCode = '';

    public string $locationFormCity = '';

    public string $locationFormDdt = '';

    public string $locationFormLatitude = '';

    public string $locationFormLongitude = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Customer::class);
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

        if ($this->editingCustomerId !== null) {
            $customer = Customer::findOrFail($this->editingCustomerId);
            $this->authorize('update', $customer);
            $update->handle($customer, $validated, (int) auth()->id());
        } else {
            $this->authorize('create', Customer::class);
            try {
                $create->handle($tenant, $validated, (int) auth()->id());
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
        $this->resetLocationForm();
        $this->showLocationModal = true;
    }

    public function closeLocationModal(): void
    {
        $this->showLocationModal = false;
        $this->locationCustomerId = null;
        $this->resetLocationForm();
    }

    public function saveLocation(CreateLocationAction $create): void
    {
        $tenant = $this->resolveTenant();
        $customer = $this->locationCustomerId !== null ? Customer::find($this->locationCustomerId) : null;
        if (! $tenant instanceof Tenant || ! $customer instanceof Customer) {
            return;
        }
        $this->authorize('update', $customer);

        $validator = Validator::make(
            [
                'name' => $this->locationFormName,
                'street' => $this->locationFormStreet,
                'house_number' => $this->locationFormHouseNumber,
                'postal_code' => $this->locationFormPostalCode,
                'city' => $this->locationFormCity,
                'contractual_relationship_reference' => $this->locationFormDdt,
                'latitude' => $this->locationFormLatitude !== '' ? $this->locationFormLatitude : null,
                'longitude' => $this->locationFormLongitude !== '' ? $this->locationFormLongitude : null,
            ],
            StoreLocationRequest::ruleSet(),
            StoreLocationRequest::messageSet(),
        );
        StoreLocationRequest::applyMinimumIdentityCheck($validator);
        $validated = $validator->validate();

        try {
            $create->handle([
                ...$validated,
                'country_code' => 'BE',
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
        $this->locationFormDdt = '';
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
