<?php

namespace App\Livewire\Pages;

use App\Actions\Settings\UpdateUserUiThemeAction;
use App\Actions\Team\UpdateOrganisationAction;
use App\Actions\Team\UpdateOrganisationLogoAction;
use App\Actions\Team\UpdateOrganisationPortalBackgroundAction;
use App\Actions\Team\UpdateTenantQrStickerSheetSettingsAction;
use App\Data\Team\UpdateTenantQrStickerSheetSettingsData;
use App\Http\Requests\Team\UpdateTenantQrStickerSheetSettingsRequest;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Enums\UiTheme;
use App\Http\Requests\Team\UpdateOrganisationRequest;
use App\Models\Tenant;
use App\Support\Platform\SupportTenantContext;
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

    public bool $customThemeActive = false;

    public string $customThemeBg = '#ffffff';

    public string $customThemeBtn = '#059669';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $orgLogo = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $portalBackground = null;

    public string $qrStickerAvery6289HeaderText = '';

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
        $this->reset('portalBackground');
        $this->resetErrorBag();
    }

    public function saveOrganisationInline(UpdateOrganisationAction $updateOrganisation): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        // Alleen de thema-velden updaten
        $payload = [
            'name' => $tenant->name, // Action vereist name
            'custom_theme_active' => $this->customThemeActive,
            'custom_theme_bg' => $this->customThemeBg,
            'custom_theme_btn' => $this->customThemeBtn,
        ];

        $updated = $updateOrganisation->handle($tenant, $payload, (int) auth()->id());
        $this->fillOrganisationFromTenant($updated);
        
        $this->dispatch('saved');
    }

    public function saveOrganisation(UpdateOrganisationAction $updateOrganisation): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        $this->orgCountryCode = strtoupper(trim($this->orgCountryCode));

        $request = new UpdateOrganisationRequest;

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
                'custom_theme_active' => $this->customThemeActive,
                'custom_theme_bg' => $this->customThemeBg,
                'custom_theme_btn' => $this->customThemeBtn,
            ],
            $request->rules(),
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
            'custom_theme_active' => $validated['custom_theme_active'] ?? false,
            'custom_theme_bg' => $validated['custom_theme_bg'] ?? null,
            'custom_theme_btn' => $validated['custom_theme_btn'] ?? null,
        ];

        $updated = $updateOrganisation->handle($tenant, $payload, (int) auth()->id());
        $this->fillOrganisationFromTenant($updated);

        $user = auth()->user();
        if ($user !== null && (int) $user->tenant_id === (int) $updated->id) {
            $user->setRelation('tenant', $updated);
        }

        $this->closeOrgModal();
        $this->dispatch('saved');
    }

    public function saveOrganisationLogo(UpdateOrganisationLogoAction $updateLogo): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        Validator::make(
            ['orgLogo' => $this->orgLogo],
            ['orgLogo' => ['required', 'image', 'max:2048']],
        )->validate();

        if (! $this->orgLogo instanceof UploadedFile) {
            return;
        }

        $updated = $updateLogo->handle($tenant, $this->orgLogo, (int) auth()->id());
        $this->reset('orgLogo');
        $this->fillOrganisationFromTenant($updated);

        $user = auth()->user();
        if ($user !== null && (int) $user->tenant_id === (int) $updated->id) {
            $user->setRelation('tenant', $updated);
        }

        $this->dispatch('saved');
    }

    public function saveQrStickerAvery6289Settings(UpdateTenantQrStickerSheetSettingsAction $updateSettings): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        $template = QrStickerSheetTemplate::Avery62x89R;

        Validator::make(
            ['headerText' => $this->qrStickerAvery6289HeaderText],
            UpdateTenantQrStickerSheetSettingsRequest::rulesFor($template),
            UpdateTenantQrStickerSheetSettingsRequest::messagesFor($template),
        )->validate();

        $updated = $updateSettings->handle(
            $tenant,
            UpdateTenantQrStickerSheetSettingsData::fromValidated($template, [
                'headerText' => $this->qrStickerAvery6289HeaderText,
            ]),
            (int) auth()->id(),
        );
        $this->fillOrganisationFromTenant($updated);

        $user = auth()->user();
        if ($user !== null && (int) $user->tenant_id === (int) $updated->id) {
            $user->setRelation('tenant', $updated);
        }

        $this->dispatch('saved');
    }

    public function saveOrganisationPortalBackground(UpdateOrganisationPortalBackgroundAction $updateBackground): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        Validator::make(
            ['portalBackground' => $this->portalBackground],
            ['portalBackground' => ['required', 'image', 'max:4096']],
        )->validate();

        if (! $this->portalBackground instanceof UploadedFile) {
            return;
        }

        $updated = $updateBackground->handle($tenant, $this->portalBackground, (int) auth()->id());
        $this->reset('portalBackground');
        $this->fillOrganisationFromTenant($updated);

        $user = auth()->user();
        if ($user !== null && (int) $user->tenant_id === (int) $updated->id) {
            $user->setRelation('tenant', $updated);
        }

        $this->dispatch('saved');
    }

    public function organisationLogoPreviewUrl(): ?string
    {
        if ($this->orgLogo !== null) {
            return $this->orgLogo->temporaryUrl();
        }

        return $this->resolveTenant()?->fresh()?->logoPublicUrl();
    }

    public function portalBackgroundPreviewUrl(): ?string
    {
        if ($this->portalBackground !== null) {
            return $this->portalBackground->temporaryUrl();
        }

        return $this->resolveTenant()?->fresh()?->portalBackgroundPublicUrl();
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
            'portalBackgroundUrl' => $this->portalBackgroundPreviewUrl(),
            'organisationTenant' => $tenant instanceof Tenant
                ? $tenant->fresh()->load('qrStickerSheetSettings')
                : null,
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
        $this->customThemeActive = (bool) $tenant->custom_theme_active;
        $this->customThemeBg = $tenant->custom_theme_bg ?? '#e7e8ec';
        $this->customThemeBtn = $tenant->custom_theme_btn ?? '#059669';
        $this->qrStickerAvery6289HeaderText = (string) (
            $tenant->qrStickerSheetSetting(QrStickerSheetTemplate::Avery62x89R)?->header_text ?? ''
        );
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
