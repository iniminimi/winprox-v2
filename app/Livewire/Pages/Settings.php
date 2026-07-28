<?php

namespace App\Livewire\Pages;

use App\Actions\Qr\RenderBrandedQrStickerPreviewAction;
use App\Actions\Settings\UpdateUserNotifyOnNewIssueEmailAction;
use App\Actions\Settings\UpdateUserUiThemeAction;
use App\Data\Qr\BrandedQrStickerPreviewData;
use App\Actions\Team\UpdateOrganisationAction;
use App\Actions\Team\UpdateOrganisationLogoAction;
use App\Actions\Team\UpdateOrganisationPortalBackgroundAction;
use App\Actions\Team\RemoveTenantQrStickerSheetBackgroundAction;
use App\Actions\Team\UpdateTenantQrPrintablePageSettingsAction;
use App\Actions\Team\UpdateTenantQrStickerSheetSettingsAction;
use App\Actions\Team\UploadTenantQrStickerSheetBackgroundAction;
use App\Data\Team\UpdateTenantQrPrintablePageSettingsData;
use App\Data\Team\UpdateTenantQrStickerSheetSettingsData;
use App\Enums\QrPrintablePageBackgroundPreset;
use App\Enums\QrStickerTenantLogoPlacement;
use App\Enums\UiTheme;
use App\Http\Requests\Team\UploadOrganisationLogoRequest;
use App\Http\Requests\Team\UploadOrganisationPortalBackgroundRequest;
use App\Http\Requests\Team\UploadTenantQrStickerSheetBackgroundRequest;
use App\Http\Requests\Team\UpdateTenantQrPrintablePageSettingsRequest;
use App\Http\Requests\Team\UpdateTenantQrStickerSheetSettingsRequest;
use App\Support\Qr\BrandedQrStickerLayoutConfig;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Http\Requests\Team\UpdateOrganisationRequest;
use App\Models\Location;
use App\Models\Tenant;
use App\Support\Admin\AdminHealthService;
use App\Support\Platform\SupportTenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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

    public string $qrStickerAvery6289TenantLogo = 'bottom_right';

    public string $qrStickerAvery6289TenantAddress = 'bottom_left';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $qrStickerAvery6289Background = null;

    public string $qrPrintableBackgroundPreset = 'blue';

    public string $qrPrintableTenantLogo = 'bottom_right';

    public string $qrPrintableTenantAddress = 'bottom_left';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $qrPrintableBackground = null;

    public string $uiTheme = '';

    public bool $notifyOnNewIssueEmail = true;

    public bool $canManageOrganisation = false;

    public bool $canUpdateTenantBranding = false;

    public bool $showOrgModal = false;

    public bool $configOverviewLoaded = false;

    public int $configIssueCount = 0;

    public function mount(AdminHealthService $healthService): void
    {
        $tenant = $this->resolveTenant();
        abort_unless($tenant instanceof Tenant, 403);

        $this->authorize('viewAny', Location::class);
        $this->configIssueCount = $healthService->issueCount();

        $user = auth()->user();
        $this->canManageOrganisation = $user->can('manageOrganisation', $tenant);
        $this->canUpdateTenantBranding = $user->can('updateTenantBranding', $tenant);
        $this->uiTheme = $user->uiThemeEnum()->value;
        $this->notifyOnNewIssueEmail = (bool) $user->notify_on_new_issue_email;

        if ($this->canUpdateTenantBranding) {
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

        $this->authorize('updateTenantBranding', $tenant);

        $payload = [
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

    public function updatedOrgLogo(): void
    {
        $this->persistOrganisationLogo(
            app(UpdateOrganisationLogoAction::class),
        );
    }

    private function persistOrganisationLogo(UpdateOrganisationLogoAction $updateLogo): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        if (! $this->orgLogo instanceof UploadedFile) {
            return;
        }

        Validator::make(
            ['orgLogo' => $this->orgLogo],
            UploadOrganisationLogoRequest::rules(),
            UploadOrganisationLogoRequest::messagesFor(),
        )->validate();

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

        $this->authorize('updateTenantBranding', $tenant);

        $template = QrStickerSheetTemplate::Avery62x89R;

        Validator::make(
            [
                'headerText' => $this->qrStickerAvery6289HeaderText,
                'tenantLogo' => $this->qrStickerAvery6289TenantLogo,
                'tenantAddress' => $this->qrStickerAvery6289TenantAddress,
            ],
            UpdateTenantQrStickerSheetSettingsRequest::rulesFor($template),
            UpdateTenantQrStickerSheetSettingsRequest::messagesFor($template),
        )->validate();

        $updated = $updateSettings->handle(
            $tenant,
            UpdateTenantQrStickerSheetSettingsData::fromValidated($template, [
                'headerText' => $this->qrStickerAvery6289HeaderText,
                'tenantLogo' => $this->qrStickerAvery6289TenantLogo,
                'tenantAddress' => $this->qrStickerAvery6289TenantAddress,
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

    public function updatedQrStickerAvery6289Background(): void
    {
        $this->persistQrStickerAvery6289Background(
            app(UploadTenantQrStickerSheetBackgroundAction::class),
        );
    }

    private function persistQrStickerAvery6289Background(
        UploadTenantQrStickerSheetBackgroundAction $uploadBackground,
    ): void {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('updateTenantBranding', $tenant);

        if (! $this->qrStickerAvery6289Background instanceof UploadedFile) {
            return;
        }

        Validator::make(
            ['background' => $this->qrStickerAvery6289Background],
            UploadTenantQrStickerSheetBackgroundRequest::rules(),
        )->validate();

        $updated = $uploadBackground->handle(
            $tenant,
            QrStickerSheetTemplate::Avery62x89R,
            $this->qrStickerAvery6289Background,
            (int) auth()->id(),
        );
        $this->reset('qrStickerAvery6289Background');
        $this->fillOrganisationFromTenant($updated);

        $user = auth()->user();
        if ($user !== null && (int) $user->tenant_id === (int) $updated->id) {
            $user->setRelation('tenant', $updated);
        }

        $this->dispatch('saved');
    }

    public function removeQrStickerAvery6289Background(RemoveTenantQrStickerSheetBackgroundAction $removeBackground): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('updateTenantBranding', $tenant);

        $updated = $removeBackground->handle(
            $tenant,
            QrStickerSheetTemplate::Avery62x89R,
            (int) auth()->id(),
        );
        $this->fillOrganisationFromTenant($updated);

        $user = auth()->user();
        if ($user !== null && (int) $user->tenant_id === (int) $updated->id) {
            $user->setRelation('tenant', $updated);
        }

        $this->dispatch('saved');
    }

    public function saveQrPrintablePageSettings(UpdateTenantQrPrintablePageSettingsAction $updateSettings): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('updateTenantBranding', $tenant);

        Validator::make(
            [
                'preset' => $this->qrPrintableBackgroundPreset,
                'tenantLogo' => $this->qrPrintableTenantLogo,
                'tenantAddress' => $this->qrPrintableTenantAddress,
            ],
            UpdateTenantQrPrintablePageSettingsRequest::rulesFor(),
        )->validate();

        $updated = $updateSettings->handle(
            $tenant,
            UpdateTenantQrPrintablePageSettingsData::fromValidated([
                'preset' => $this->qrPrintableBackgroundPreset,
                'tenantLogo' => $this->qrPrintableTenantLogo,
                'tenantAddress' => $this->qrPrintableTenantAddress,
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

    public function updatedQrPrintableBackground(): void
    {
        $this->persistQrPrintableBackground(
            app(UploadTenantQrStickerSheetBackgroundAction::class),
        );
    }

    private function persistQrPrintableBackground(
        UploadTenantQrStickerSheetBackgroundAction $uploadBackground,
    ): void {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('updateTenantBranding', $tenant);

        if (! $this->qrPrintableBackground instanceof UploadedFile) {
            return;
        }

        Validator::make(
            ['background' => $this->qrPrintableBackground],
            UploadTenantQrStickerSheetBackgroundRequest::rules(),
        )->validate();

        $updated = $uploadBackground->handle(
            $tenant,
            QrStickerSheetTemplate::printablePageSettings(),
            $this->qrPrintableBackground,
            (int) auth()->id(),
        );
        $this->reset('qrPrintableBackground');
        $this->fillOrganisationFromTenant($updated);

        $user = auth()->user();
        if ($user !== null && (int) $user->tenant_id === (int) $updated->id) {
            $user->setRelation('tenant', $updated);
        }

        $this->dispatch('saved');
    }

    public function removeQrPrintableBackground(RemoveTenantQrStickerSheetBackgroundAction $removeBackground): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('updateTenantBranding', $tenant);

        $updated = $removeBackground->handle(
            $tenant,
            QrStickerSheetTemplate::printablePageSettings(),
            (int) auth()->id(),
        );
        $this->fillOrganisationFromTenant($updated);

        $user = auth()->user();
        if ($user !== null && (int) $user->tenant_id === (int) $updated->id) {
            $user->setRelation('tenant', $updated);
        }

        $this->dispatch('saved');
    }

    public function updatedPortalBackground(): void
    {
        $this->persistOrganisationPortalBackground(
            app(UpdateOrganisationPortalBackgroundAction::class),
        );
    }

    private function persistOrganisationPortalBackground(UpdateOrganisationPortalBackgroundAction $updateBackground): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('updateTenantBranding', $tenant);

        if (! $this->portalBackground instanceof UploadedFile) {
            return;
        }

        Validator::make(
            ['portalBackground' => $this->portalBackground],
            UploadOrganisationPortalBackgroundRequest::rules(),
            UploadOrganisationPortalBackgroundRequest::messagesFor(),
        )->validate();

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
            return $this->livewireUploadPreviewUrl($this->orgLogo);
        }

        return $this->resolveTenant()?->fresh()?->logoPublicUrl();
    }

    public function portalBackgroundPreviewUrl(): ?string
    {
        if ($this->portalBackground !== null) {
            return $this->livewireUploadPreviewUrl($this->portalBackground);
        }

        return $this->resolveTenant()?->fresh()?->portalBackgroundPublicUrl();
    }

    private function livewireUploadPreviewUrl(UploadedFile $file): ?string
    {
        if (! $file instanceof TemporaryUploadedFile || ! $file->isPreviewable()) {
            return null;
        }

        return $file->temporaryUrl();
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

    public function updatedNotifyOnNewIssueEmail(bool $value, UpdateUserNotifyOnNewIssueEmailAction $updatePreference): void
    {
        abort_unless($this->resolveTenant() instanceof Tenant, 403);

        $user = auth()->user();
        $updated = $updatePreference->handle($user, $value, (int) $user->id);
        $this->notifyOnNewIssueEmail = (bool) $updated->notify_on_new_issue_email;
    }

    public function loadConfigOverview(AdminHealthService $healthService): void
    {
        abort_unless($this->resolveTenant() instanceof Tenant, 403);

        $this->authorize('viewAny', Location::class);

        if ($this->configOverviewLoaded) {
            return;
        }

        $this->configOverviewLoaded = true;
        $this->configIssueCount = $healthService->issueCount();
    }

    public function render(AdminHealthService $healthService)
    {
        $tenant = $this->resolveTenant();

        $configSummary = $this->configOverviewLoaded
            ? $healthService->summary()
            : null;

        return view('livewire.pages.settings', [
            'configSummary' => $configSummary,
            'themeChoices' => UiTheme::choices(),
            'organisationLogoUrl' => $this->organisationLogoPreviewUrl(),
            'portalBackgroundUrl' => $this->portalBackgroundPreviewUrl(),
            'organisationTenant' => $tenant instanceof Tenant
                ? $tenant->fresh()->load('qrStickerSheetSettings')
                : null,
            'qrStickerTenantLogoChoices' => QrStickerTenantLogoPlacement::choices(),
            'qrStickerPreviewDataUrl' => $this->resolveQrStickerPreviewDataUrl(),
            'qrPrintableBackgroundPresets' => QrPrintablePageBackgroundPreset::choices(),
            'qrPrintableBackgroundPreviewUrl' => $this->resolveQrPrintableBackgroundPreviewUrl(),
        ]);
    }

    private function resolveQrPrintableBackgroundPreviewUrl(): ?string
    {
        if (! $this->canUpdateTenantBranding) {
            return null;
        }

        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return null;
        }

        $tenant->loadMissing('qrStickerSheetSettings');
        $sheetSetting = $tenant->qrStickerSheetSetting(QrStickerSheetTemplate::printablePageSettings());
        $uploadUrl = $sheetSetting?->backgroundPublicUrl();
        if (is_string($uploadUrl) && $uploadUrl !== '') {
            return $uploadUrl;
        }

        $preset = QrPrintablePageBackgroundPreset::tryFrom($this->qrPrintableBackgroundPreset)
            ?? QrPrintablePageBackgroundPreset::fromSetting($sheetSetting);

        return $preset->publicUrl();
    }

    /** Alleen bij render — nooit als public Livewire-state (base64 > 1 MB breekt requests). */
    private function resolveQrStickerPreviewDataUrl(): ?string
    {
        if (! $this->canUpdateTenantBranding) {
            return null;
        }

        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return null;
        }

        $tenant = $tenant->fresh()->load('qrStickerSheetSettings');
        $sheetSetting = $tenant->qrStickerSheetSetting(QrStickerSheetTemplate::Avery62x89R);

        return app(RenderBrandedQrStickerPreviewAction::class)->handle(
            $tenant,
            BrandedQrStickerPreviewData::fromLivewireForm(
                $this->qrStickerAvery6289HeaderText,
                $this->qrStickerAvery6289TenantLogo,
                $this->qrStickerAvery6289TenantAddress,
            ),
            $sheetSetting,
        );
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
        $tenant->loadMissing('qrStickerSheetSettings');
        $sheetSetting = $tenant->qrStickerSheetSetting(QrStickerSheetTemplate::Avery62x89R);
        $layout = BrandedQrStickerLayoutConfig::fromSetting($sheetSetting);

        $this->qrStickerAvery6289HeaderText = (string) ($sheetSetting?->header_text ?? '');
        $this->qrStickerAvery6289TenantLogo = $layout->tenantLogoPlacement()->value;
        $this->qrStickerAvery6289TenantAddress = $layout->tenantAddressPlacement()->value;

        $printableSetting = $tenant->qrStickerSheetSetting(QrStickerSheetTemplate::printablePageSettings());
        $printableLayout = BrandedQrStickerLayoutConfig::fromSetting($printableSetting);
        $this->qrPrintableBackgroundPreset = QrPrintablePageBackgroundPreset::fromSetting($printableSetting)->value;
        $this->qrPrintableTenantLogo = $printableLayout->tenantLogoPlacement()->value;
        $this->qrPrintableTenantAddress = $printableLayout->tenantAddressPlacement()->value;
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
