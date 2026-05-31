<?php

namespace App\Livewire\Pages;

use App\Actions\Settings\UpdateUserUiThemeAction;
use App\Actions\Team\UpdateOrganisationAction;
use App\Enums\UiTheme;
use App\Http\Requests\Team\UpdateOrganisationRequest;
use App\Models\Tenant;
use App\Support\Platform\SupportTenantContext;
use App\Support\TenantLogoStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Settings extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public string $orgName = '';

    public string $orgEmail = '';

    public string $orgPhone = '';

    public string $orgStreet = '';

    public string $orgHouseNumber = '';

    public string $orgPostalCode = '';

    public string $orgCity = '';

    public string $orgCountryCode = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $orgLogo = null;

    public string $uiTheme = '';

    public bool $canManageOrganisation = false;

    public bool $showOrgModal = false;

    public function mount(): void
    {
        $tenant = $this->resolveTenant();
        abort_unless($tenant instanceof Tenant, 403);

        $user = auth()->user();
        $this->canManageOrganisation = $user->can('manageOrganisation', $tenant);
        $this->uiTheme = $user->uiThemeEnum()->value;

        if ($this->canManageOrganisation) {
            $this->fillOrganisationFromTenant($tenant);
        }
    }

    public function openOrgModal(): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        $this->fillOrganisationFromTenant($tenant->fresh());
        $this->reset('orgLogo');
        $this->resetErrorBag();
        $this->showOrgModal = true;
    }

    public function closeOrgModal(): void
    {
        $this->showOrgModal = false;
        $this->reset('orgLogo');
        $this->resetErrorBag();
    }

    public function saveOrganisation(UpdateOrganisationAction $updateOrganisation, TenantLogoStorage $logoStorage): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        $this->orgCountryCode = strtoupper(trim($this->orgCountryCode));

        $request = new UpdateOrganisationRequest;
        $rules = $request->rules();
        if ($this->orgLogo !== null) {
            $rules['orgLogo'] = ['nullable', 'image', 'max:2048'];
        }

        $validated = Validator::make(
            [
                'name' => $this->orgName,
                'email' => $this->orgEmail,
                'phone' => $this->orgPhone,
                'street' => $this->orgStreet,
                'house_number' => $this->orgHouseNumber,
                'postal_code' => $this->orgPostalCode,
                'city' => $this->orgCity,
                'country_code' => $this->orgCountryCode,
                'orgLogo' => $this->orgLogo,
            ],
            $rules,
            $request->messages(),
        )->validate();

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'street' => $validated['street'] ?? null,
            'house_number' => $validated['house_number'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'city' => $validated['city'] ?? null,
            'country_code' => $validated['country_code'] ?? null,
        ];

        if ($this->orgLogo instanceof UploadedFile) {
            $logoStorage->delete($tenant->logo_path);
            $payload['logo_path'] = $logoStorage->store($this->orgLogo, (int) $tenant->id);
            $this->reset('orgLogo');
        }

        $updated = $updateOrganisation->handle($tenant, $payload, (int) auth()->id());
        $this->fillOrganisationFromTenant($updated);

        $user = auth()->user();
        if ($user !== null && (int) $user->tenant_id === (int) $updated->id) {
            $user->setRelation('tenant', $updated);
        }

        $this->closeOrgModal();
        $this->dispatch('saved');
    }

    public function organisationLogoPreviewUrl(): ?string
    {
        if ($this->orgLogo !== null) {
            return $this->orgLogo->temporaryUrl();
        }

        return $this->resolveTenant()?->fresh()?->logoPublicUrl();
    }

    public function updatedUiTheme(string $value, UpdateUserUiThemeAction $updateUserUiTheme): void
    {
        abort_unless($this->resolveTenant() instanceof Tenant, 403);

        $theme = UiTheme::tryFromString($value);
        $user = auth()->user();

        $updateUserUiTheme->handle($user, $theme, (int) $user->id);

        $this->uiTheme = $theme->value;
        $this->dispatch('ui-theme-changed', theme: $theme->value);
    }

    public function render()
    {
        $tenant = $this->resolveTenant();

        return view('livewire.pages.settings', [
            'themeChoices' => UiTheme::choices(),
            'organisationLogoUrl' => $this->organisationLogoPreviewUrl(),
            'organisationTenant' => $tenant instanceof Tenant ? $tenant->fresh() : null,
        ]);
    }

    private function fillOrganisationFromTenant(Tenant $tenant): void
    {
        $this->orgName = trim((string) $tenant->name);
        $this->orgEmail = (string) ($tenant->email ?? '');
        $this->orgPhone = (string) ($tenant->phone ?? '');
        $this->orgStreet = (string) ($tenant->street ?? '');
        $this->orgHouseNumber = (string) ($tenant->house_number ?? '');
        $this->orgPostalCode = (string) ($tenant->postal_code ?? '');
        $this->orgCity = (string) ($tenant->city ?? '');
        $this->orgCountryCode = $tenant->country_code
            ? strtoupper((string) $tenant->country_code)
            : '';
    }

    private function resolveTenant(): ?Tenant
    {
        $user = auth()->user();
        if ($user->tenant instanceof Tenant) {
            return $user->tenant;
        }

        if ($user->is_superuser && SupportTenantContext::isActive()) {
            return Tenant::query()->find(SupportTenantContext::activeTenantId());
        }

        return null;
    }
}
