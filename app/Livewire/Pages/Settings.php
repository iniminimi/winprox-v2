<?php

namespace App\Livewire\Pages;

use App\Actions\Qr\RenderBrandedQrStickerPreviewAction;
use App\Actions\Settings\UpdateUserNotifyOnNewIssueEmailAction;
use App\Actions\Settings\UpdateUserUiThemeAction;
use App\Data\Qr\BrandedQrStickerPreviewData;
use App\Actions\Team\UpdateOrganisationAction;
use App\Actions\Team\UpdateOrganisationLogoAction;
use App\Actions\Team\UpdateOrganisationPortalBackgroundAction;
use App\Actions\Team\UpdateTenantWorkMenuAction;
use App\Actions\Time\UpdatePresenceComplianceSettingsAction;
use App\Actions\Time\UpdateTenantTimeClockSecurityAction;
use App\Enums\PresenceComplianceScope;
use App\Http\Requests\Time\UpdatePresenceComplianceSettingsRequest;
use App\Http\Requests\Time\UpdateTenantTimeClockSecurityRequest;
use App\Models\PresenceSubmission;
use App\Actions\Team\RemoveOrganisationPortalBackgroundAction;
use App\Actions\Team\SetOrganisationPortalStockBackgroundAction;
use App\Actions\Team\RemoveTenantQrStickerSheetBackgroundAction;
use App\Actions\Team\UpdateTenantQrPrintablePageSettingsAction;
use App\Actions\Team\UpdateTenantQrStickerSheetSettingsAction;
use App\Actions\Team\UploadTenantQrStickerSheetBackgroundAction;
use App\Data\Team\UpdateTenantQrPrintablePageSettingsData;
use App\Data\Team\UpdateTenantQrStickerSheetSettingsData;
use App\Enums\QrPrintablePageBackgroundPreset;
use App\Enums\QrStickerTenantLogoPlacement;
use App\Enums\UiTheme;
use App\Http\Requests\Team\SetOrganisationPortalStockBackgroundRequest;
use App\Http\Requests\Team\UploadOrganisationLogoRequest;
use App\Http\Requests\Team\UploadOrganisationPortalBackgroundRequest;
use App\Http\Requests\Team\UploadTenantQrStickerSheetBackgroundRequest;
use App\Http\Requests\Team\UpdateTenantQrPrintablePageSettingsRequest;
use App\Http\Requests\Team\UpdateTenantQrStickerSheetSettingsRequest;
use App\Support\Qr\BrandedQrStickerLayoutConfig;
use App\Support\Qr\QrPrintablePageBackground;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\TenantPortalBackground;
use App\Http\Requests\Team\UpdateOrganisationRequest;
use App\Http\Requests\Team\UpdateTenantWorkMenuRequest;
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

    public string $portalBackgroundStockPreset = '';

    public string $qrBrandingHeaderText = '';

    public string $qrBrandingTenantLogo = 'bottom_right';

    public string $qrBrandingTenantAddress = 'bottom_left';

    public string $qrBrandingBackgroundPreset = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $qrBrandingBackground = null;

    public string $uiTheme = '';

    public bool $notifyOnNewIssueEmail = true;

    public bool $workMenuCalendarEnabled = true;

    public bool $workMenuReservationsEnabled = true;

    public bool $workMenuInspectionRoundsEnabled = true;

    public bool $workMenuUnitMeasurementsEnabled = true;

    public bool $presenceComplianceEnabled = false;

    public bool $timeRequireWorkerPin = false;

    public bool $timeGpsOnClock = false;

    public string $presenceComplianceScope = 'ciao_cleaning';

    public string $enterpriseNumber = '';

    public string $foreignVatNumber = '';

    public string $presenceRszClientId = '';

    public string $presenceRszPrivateKey = '';

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

        if ($this->canManageOrganisation) {
            $this->fillWorkMenuFromTenant($tenant);
        }
    }

    public function saveWorkMenuSettings(): void
    {
        $this->persistWorkMenuSettings();
        $this->redirect(route('settings.index'), navigate: false);
    }

    public function savePresenceCompliance(UpdatePresenceComplianceSettingsAction $update): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        $validated = Validator::make(
            [
                'presence_compliance_enabled' => $this->presenceComplianceEnabled,
                'presence_compliance_scope' => $this->presenceComplianceScope !== ''
                    ? $this->presenceComplianceScope
                    : null,
                'enterprise_number' => $this->enterpriseNumber,
                'foreign_vat_number' => $this->foreignVatNumber,
                'presence_rsz_client_id' => $this->presenceRszClientId,
                'presence_rsz_private_key' => $this->presenceRszPrivateKey,
            ],
            UpdatePresenceComplianceSettingsRequest::ruleSet(),
            UpdatePresenceComplianceSettingsRequest::messageSet(),
        )->validate();

        try {
            $updated = $update->handle($tenant, $validated, (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'time_module_disabled') {
                $this->addError('presenceComplianceEnabled', __('settings.errors.time_module_required'));

                return;
            }
            if ($e->getMessage() === 'presence_scope_unavailable') {
                $this->addError('presenceComplianceScope', __('settings.errors.presence_scope_invalid'));

                return;
            }

            throw $e;
        }

        $this->fillOrganisationFromTenant($updated);
        $this->dispatch('saved');
        session()->flash('success', __('settings.presence.saved'));
    }

    public function saveTimeClockSecurity(UpdateTenantTimeClockSecurityAction $update): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        $validated = Validator::make(
            [
                'time_require_worker_pin' => $this->timeRequireWorkerPin,
                'time_gps_on_clock' => $this->timeGpsOnClock,
            ],
            UpdateTenantTimeClockSecurityRequest::ruleSet(),
        )->validate();

        try {
            $updated = $update->handle($tenant, $validated, (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'time_module_disabled') {
                $this->addError('timeRequireWorkerPin', __('settings.errors.time_module_required'));

                return;
            }

            throw $e;
        }

        $this->fillOrganisationFromTenant($updated);
        $this->dispatch('saved');
        session()->flash('success', __('settings.time_clock.saved'));
    }

    private function persistWorkMenuSettings(): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageWorkMenu', $tenant);

        $validated = Validator::make(
            [
                'work_menu_calendar_enabled' => $this->workMenuCalendarEnabled,
                'work_menu_reservations_enabled' => $this->workMenuReservationsEnabled,
                'work_menu_inspection_rounds_enabled' => $this->workMenuInspectionRoundsEnabled,
                'work_menu_unit_measurements_enabled' => $this->workMenuUnitMeasurementsEnabled,
            ],
            UpdateTenantWorkMenuRequest::ruleSet(),
        )->validate();

        $updated = app(UpdateTenantWorkMenuAction::class)->handle($tenant, $validated, (int) auth()->id());
        $this->fillWorkMenuFromTenant($updated);
        $this->dispatch('saved');
    }

    private function fillWorkMenuFromTenant(Tenant $tenant): void
    {
        $this->workMenuCalendarEnabled = $tenant->workMenuCalendarEnabled();
        $this->workMenuReservationsEnabled = $tenant->workMenuReservationsEnabled();
        $this->workMenuInspectionRoundsEnabled = $tenant->workMenuInspectionRoundsEnabled();
        $this->workMenuUnitMeasurementsEnabled = $tenant->workMenuUnitMeasurementsEnabled();
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
            'custom_theme_active' => true,
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

    public function saveQrBrandingSettings(
        UpdateTenantQrStickerSheetSettingsAction $updateStickerSettings,
        UpdateTenantQrPrintablePageSettingsAction $updatePrintableSettings,
    ): void {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('updateTenantBranding', $tenant);

        $averyTemplate = QrStickerSheetTemplate::Avery62x89R;

        Validator::make(
            [
                'headerText' => $this->qrBrandingHeaderText,
                'tenantLogo' => $this->qrBrandingTenantLogo,
                'tenantAddress' => $this->qrBrandingTenantAddress,
                'preset' => $this->qrBrandingBackgroundPreset,
            ],
            array_merge(
                UpdateTenantQrStickerSheetSettingsRequest::rulesFor($averyTemplate),
                UpdateTenantQrPrintablePageSettingsRequest::rulesFor(),
            ),
            UpdateTenantQrStickerSheetSettingsRequest::messagesFor($averyTemplate),
        )->validate();

        $updated = $updateStickerSettings->handle(
            $tenant,
            UpdateTenantQrStickerSheetSettingsData::fromValidated($averyTemplate, [
                'headerText' => $this->qrBrandingHeaderText,
                'tenantLogo' => $this->qrBrandingTenantLogo,
                'tenantAddress' => $this->qrBrandingTenantAddress,
            ]),
            (int) auth()->id(),
        );

        $updated = $updatePrintableSettings->handle(
            $updated,
            UpdateTenantQrPrintablePageSettingsData::fromValidated([
                'preset' => $this->qrBrandingBackgroundPreset,
                'headerText' => $this->qrBrandingHeaderText,
                'tenantLogo' => $this->qrBrandingTenantLogo,
                'tenantAddress' => $this->qrBrandingTenantAddress,
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

    public function updatedQrBrandingBackgroundPreset(
        UpdateTenantQrPrintablePageSettingsAction $updatePrintableSettings,
    ): void {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('updateTenantBranding', $tenant);

        Validator::make(
            [
                'preset' => $this->qrBrandingBackgroundPreset,
                'headerText' => $this->qrBrandingHeaderText,
                'tenantLogo' => $this->qrBrandingTenantLogo,
                'tenantAddress' => $this->qrBrandingTenantAddress,
            ],
            UpdateTenantQrPrintablePageSettingsRequest::rulesFor(),
        )->validate();

        $updated = $updatePrintableSettings->handle(
            $tenant,
            UpdateTenantQrPrintablePageSettingsData::fromValidated([
                'preset' => $this->qrBrandingBackgroundPreset,
                'headerText' => $this->qrBrandingHeaderText,
                'tenantLogo' => $this->qrBrandingTenantLogo,
                'tenantAddress' => $this->qrBrandingTenantAddress,
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

    public function updatedQrBrandingBackground(): void
    {
        $this->persistQrBrandingBackground(
            app(UploadTenantQrStickerSheetBackgroundAction::class),
        );
    }

    private function persistQrBrandingBackground(
        UploadTenantQrStickerSheetBackgroundAction $uploadBackground,
    ): void {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('updateTenantBranding', $tenant);

        if (! $this->qrBrandingBackground instanceof UploadedFile) {
            return;
        }

        Validator::make(
            ['background' => $this->qrBrandingBackground],
            UploadTenantQrStickerSheetBackgroundRequest::rules(),
        )->validate();

        $updated = $uploadBackground->handle(
            $tenant,
            QrStickerSheetTemplate::Avery62x89R,
            $this->qrBrandingBackground,
            (int) auth()->id(),
        );

        $updated = $uploadBackground->handle(
            $updated,
            QrStickerSheetTemplate::printablePageSettings(),
            $this->qrBrandingBackground,
            (int) auth()->id(),
        );

        $this->reset('qrBrandingBackground');
        $this->fillOrganisationFromTenant($updated);

        $user = auth()->user();
        if ($user !== null && (int) $user->tenant_id === (int) $updated->id) {
            $user->setRelation('tenant', $updated);
        }

        $this->dispatch('saved');
    }

    public function removeQrBrandingBackground(RemoveTenantQrStickerSheetBackgroundAction $removeBackground): void
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

        $updated = $removeBackground->handle(
            $updated,
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

    public function updatedPortalBackgroundStockPreset(
        SetOrganisationPortalStockBackgroundAction $setStock,
        RemoveOrganisationPortalBackgroundAction $removeBackground,
    ): void {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('updateTenantBranding', $tenant);

        if ($this->portalBackgroundStockPreset === '') {
            if (TenantPortalBackground::isStockPath($tenant->portal_background_path)) {
                $updated = $removeBackground->handle($tenant, (int) auth()->id());
                $this->fillOrganisationFromTenant($updated);
                $this->dispatch('saved');
            }

            return;
        }

        Validator::make(
            ['portalBackgroundStockPreset' => $this->portalBackgroundStockPreset],
            SetOrganisationPortalStockBackgroundRequest::ruleSet(),
        )->validate();

        $updated = $setStock->handle(
            $tenant,
            $this->portalBackgroundStockPreset,
            (int) auth()->id(),
        );
        $this->reset('portalBackground');
        $this->fillOrganisationFromTenant($updated);
        $this->dispatch('saved');
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

    public function removeOrganisationPortalBackground(RemoveOrganisationPortalBackgroundAction $removeBackground): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('updateTenantBranding', $tenant);

        $updated = $removeBackground->handle($tenant, (int) auth()->id());
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
            'qrBrandingPreviewDataUrl' => $this->resolveQrBrandingPreviewDataUrl(),
            'qrPrintableBackgroundPresets' => QrPrintablePageBackgroundPreset::uiChoices(),
            'hasTimeModule' => $tenant instanceof Tenant && $tenant->hasTimeModule(),
            'hasRszCredentials' => $tenant instanceof Tenant && filled($tenant->presence_rsz_client_id),
            'availablePresenceScopes' => PresenceComplianceScope::availableCases(),
            'recentPresenceSubmissions' => $tenant instanceof Tenant && $tenant->hasTimeModule()
                ? PresenceSubmission::query()
                    ->where('tenant_id', $tenant->id)
                    ->with('worker')
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
                : collect(),
        ]);
    }

    /** Alleen bij render — nooit als public Livewire-state (base64 > 1 MB breekt requests). */
    private function resolveQrBrandingPreviewDataUrl(): ?string
    {
        if (! $this->canUpdateTenantBranding) {
            return null;
        }

        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return null;
        }

        $tenant = $tenant->fresh()->load('qrStickerSheetSettings');
        $averySetting = $tenant->qrStickerSheetSetting(QrStickerSheetTemplate::Avery62x89R);
        $printableSetting = $tenant->qrStickerSheetSetting(QrStickerSheetTemplate::printablePageSettings());

        $backgroundPathOverride = $averySetting?->backgroundAbsolutePath()
            ?? $printableSetting?->backgroundAbsolutePath()
            ?? QrPrintablePageBackground::absolutePathForPresetKey($this->qrBrandingBackgroundPreset);

        return app(RenderBrandedQrStickerPreviewAction::class)->handle(
            $tenant,
            BrandedQrStickerPreviewData::fromLivewireForm(
                $this->qrBrandingHeaderText,
                $this->qrBrandingTenantLogo,
                $this->qrBrandingTenantAddress,
            ),
            $averySetting,
            $backgroundPathOverride,
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
        $this->presenceComplianceEnabled = (bool) $tenant->presence_compliance_enabled;
        $this->timeRequireWorkerPin = (bool) $tenant->time_require_worker_pin;
        $this->timeGpsOnClock = (bool) $tenant->time_gps_on_clock;
        $this->presenceComplianceScope = (string) ($tenant->presence_compliance_scope
            ?? PresenceComplianceScope::CiaoCleaning->value);
        $this->enterpriseNumber = (string) ($tenant->enterprise_number ?? '');
        $this->foreignVatNumber = (string) ($tenant->foreign_vat_number ?? '');
        $this->presenceRszClientId = '';
        $this->presenceRszPrivateKey = '';
        $this->portalBackgroundStockPreset = TenantPortalBackground::stockPresetKeyFromPath(
            $tenant->portal_background_path,
        ) ?? '';
        $tenant->loadMissing('qrStickerSheetSettings');
        $sheetSetting = $tenant->qrStickerSheetSetting(QrStickerSheetTemplate::Avery62x89R);
        $layout = BrandedQrStickerLayoutConfig::fromSetting($sheetSetting);

        $printableSetting = $tenant->qrStickerSheetSetting(QrStickerSheetTemplate::printablePageSettings());
        $printableLayout = BrandedQrStickerLayoutConfig::fromSetting($printableSetting);

        $this->qrBrandingHeaderText = (string) ($sheetSetting?->header_text ?? $printableSetting?->header_text ?? '');
        $this->qrBrandingTenantLogo = $printableLayout->tenantLogoPlacement()->value;
        $this->qrBrandingTenantAddress = $printableLayout->tenantAddressPlacement()->value;
        $this->qrBrandingBackgroundPreset = QrPrintablePageBackgroundPreset::presetKeyFromSetting($printableSetting);

        if ($printableLayout->usesDefaults() && ! $layout->usesDefaults()) {
            $this->qrBrandingTenantLogo = $layout->tenantLogoPlacement()->value;
            $this->qrBrandingTenantAddress = $layout->tenantAddressPlacement()->value;
        }
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
