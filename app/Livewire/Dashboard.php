<?php

namespace App\Livewire;

use App\Actions\Auth\DecideTenantPhoneLoginAction;
use App\Actions\Auth\ListSecurityAlertsAction;
use App\Actions\Billing\ApplyPlanEntitlementsAction;
use App\Actions\Billing\RealignSubscriptionPeriodAction;
use App\Actions\Checkmate\BuildCheckmateDashboardDataAction;
use App\Actions\Dashboard\BuildDashboardIntentHubAction;
use App\Actions\Dashboard\BuildDashboardStatsAction;
use App\Actions\Dashboard\HideCheckmateDashboardTileAction;
use App\Actions\Dashboard\HideDashboardIntentTileAction;
use App\Actions\Dashboard\HideDashboardKpiAction;
use App\Actions\Dashboard\RestoreCheckmateDashboardTileAction;
use App\Actions\Dashboard\RestoreDashboardIntentTileAction;
use App\Actions\Dashboard\RestoreDashboardKpiAction;
use App\Actions\Dashboard\SaveDashboardIntentOrderAction;
use App\Actions\Dashboard\ListDashboardRecentIssuesAction;
use App\Actions\Onboarding\ApplyTenantStarterPackAction;
use App\Actions\Onboarding\DismissTenantStarterPackResultAction;
use App\Actions\Onboarding\RemoveTenantStarterPackAction;
use App\Actions\ReleaseNotes\ShouldShowWhatsNewCardAction;
use App\Actions\Sense\ListSenseAttentionAction;
use App\Data\Sense\SenseAttentionBoard;
use App\Actions\Retention\ListTimeArchiveNoticesAction;
use App\Actions\Time\CountExpiringAbsencePhotosAction;
use App\Actions\Time\EnsureDefaultClockPointAction;
use App\Actions\Time\SendClockPointQrMailAction;
use App\Data\Onboarding\ApplyTenantStarterPackData;
use App\Data\Time\SendClockPointQrMailData;
use App\Enums\TenantStarterPackSize;
use App\Enums\TenantStarterPackType;
use App\Http\Requests\Onboarding\ApplyTenantStarterPackRequest;
use App\Http\Requests\Time\SendClockPointQrMailRequest;
use App\Livewire\Concerns\ManagesManualClockIn;
use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\SensePoint;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Checkmate\CheckmateMode;
use App\Support\Onboarding\TenantOnboardingState;
use App\Support\Onboarding\TenantStarterPackCatalog;
use App\Support\Onboarding\TenantStarterPackSummary;
use App\Support\Platform\SupportTenantContext;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Tenancy;
use App\Support\Translation\LocaleSupport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Dashboard extends Component
{
    use AuthorizesRequests;
    use ManagesManualClockIn;

    public bool $showRemoveStarterPackModal = false;

    public bool $skipStarterPack = false;

    public string $starterPackIntent = '';

    public string $starterPackType = '';

    public string $starterPackSize = '';

    public bool $showCheckmateClockPointQrModal = false;

    public ?int $checkmateQrClockPointId = null;

    public string $checkmateQrMailEmail = '';

    public ?string $checkmateQrMailFlash = null;

    public bool $showHiddenTiles = false;

    public bool $showHiddenKpis = false;

    public bool $showHiddenCheckmateTiles = false;

    public function openAddDevice(): void
    {
        $this->authorize('pairDeviceAny', Location::class);
        $this->dispatch('open-add-device');
    }

    /**
     * @param  list<mixed>  $keys
     */
    public function reorderIntentTiles(array $keys): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        app(SaveDashboardIntentOrderAction::class)->handle($user, $keys);
    }

    public function openHiddenTiles(): void
    {
        abort_unless(auth()->user() instanceof User, 403);
        $this->showHiddenTiles = true;
    }

    public function closeHiddenTiles(): void
    {
        $this->showHiddenTiles = false;
    }

    public function hideIntentTile(string $key): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        app(HideDashboardIntentTileAction::class)->handle($user, $key);
        $this->showHiddenTiles = false;
    }

    /**
     * @param  list<mixed>  $order
     */
    public function restoreIntentTile(string $key, array $order): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        app(RestoreDashboardIntentTileAction::class)->handle($user, $key, $order);
        $this->showHiddenTiles = false;
    }

    public function openHiddenKpis(): void
    {
        abort_unless(auth()->user() instanceof User, 403);
        $this->showHiddenKpis = true;
    }

    public function closeHiddenKpis(): void
    {
        $this->showHiddenKpis = false;
    }

    public function hideDashboardKpi(string $key): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        app(HideDashboardKpiAction::class)->handle($user, $key);
        $this->showHiddenKpis = false;
    }

    public function restoreDashboardKpi(string $key): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        app(RestoreDashboardKpiAction::class)->handle($user, $key);
        $this->showHiddenKpis = false;
    }

    public function openHiddenCheckmateTiles(): void
    {
        $this->abortUnlessCheckmate();
        $this->showHiddenCheckmateTiles = true;
    }

    public function closeHiddenCheckmateTiles(): void
    {
        $this->showHiddenCheckmateTiles = false;
    }

    public function hideCheckmateTile(string $key): void
    {
        $user = $this->abortUnlessCheckmate();

        app(HideCheckmateDashboardTileAction::class)->handle($user, $key);
        $this->showHiddenCheckmateTiles = false;
    }

    public function restoreCheckmateTile(string $key): void
    {
        $user = $this->abortUnlessCheckmate();

        app(RestoreCheckmateDashboardTileAction::class)->handle($user, $key);
        $this->showHiddenCheckmateTiles = false;
    }

    public function openCheckmateClockPointQr(EnsureDefaultClockPointAction $ensureDefault): void
    {
        $tenant = $this->resolveTenant();
        abort_unless($tenant instanceof Tenant && CheckmateMode::isActive($tenant), 403);
        $this->authorize('viewAny', ClockPoint::class);

        $clockPoint = $ensureDefault->handle(
            $tenant,
            __('team.clock_point_qr.default_name'),
            auth()->id(),
        );
        $this->authorize('view', $clockPoint);

        $this->checkmateQrClockPointId = (int) $clockPoint->id;
        $this->checkmateQrMailEmail = '';
        $this->checkmateQrMailFlash = null;
        $this->resetErrorBag('checkmateQrMailEmail');
        $this->showCheckmateClockPointQrModal = true;
    }

    public function closeCheckmateClockPointQr(): void
    {
        $this->showCheckmateClockPointQrModal = false;
        $this->checkmateQrClockPointId = null;
        $this->checkmateQrMailEmail = '';
        $this->checkmateQrMailFlash = null;
        $this->resetErrorBag('checkmateQrMailEmail');
    }

    public function sendCheckmateClockPointQrMail(SendClockPointQrMailAction $send): void
    {
        $tenant = $this->resolveTenant();
        abort_unless($tenant instanceof Tenant && CheckmateMode::isActive($tenant), 403);
        abort_unless($this->checkmateQrClockPointId !== null, 404);

        $clockPoint = ClockPoint::query()->findOrFail($this->checkmateQrClockPointId);
        $this->authorize('view', $clockPoint);

        $validated = $this->validate([
            'checkmateQrMailEmail' => SendClockPointQrMailRequest::rulesFor()['email'],
        ], [], [
            'checkmateQrMailEmail' => __('time.clock_points.qr.email.label'),
        ]);

        $actor = auth()->user();
        $supported = config('locales.supported', []);
        $locale = in_array((string) ($actor?->locale), $supported, true)
            ? (string) $actor->locale
            : (string) app()->getLocale();

        try {
            $send->handle(
                $clockPoint,
                new SendClockPointQrMailData((string) $validated['checkmateQrMailEmail']),
                (int) $tenant->id,
                $actor?->id,
                $locale,
            );
        } catch (ValidationException $exception) {
            $emailErrors = $exception->errors()['email'] ?? null;
            if ($emailErrors !== null) {
                throw ValidationException::withMessages([
                    'checkmateQrMailEmail' => $emailErrors,
                ]);
            }

            throw $exception;
        }

        $this->checkmateQrMailEmail = '';
        $this->checkmateQrMailFlash = __('time.clock_points.qr.email.sent');
    }

    public function openStarterPackModal(): void
    {
        $this->authorize('applyStarterPack', $this->starterPackTenant());

        $this->resetValidation();
        $this->starterPackIntent = '';
        $this->starterPackType = '';
        $this->starterPackSize = '';
        $this->skipStarterPack = false;
    }

    public function skipStarterPackChooser(): void
    {
        $this->authorize('applyStarterPack', $this->starterPackTenant());

        $this->skipStarterPack = true;
        $this->starterPackIntent = '';
        $this->starterPackType = '';
        $this->starterPackSize = '';
        $this->resetValidation();
    }

    public function closeStarterPackModal(): void
    {
        $this->skipStarterPackChooser();
    }

    public function updatedStarterPackIntent(): void
    {
        $this->starterPackType = match ($this->starterPackIntent) {
            'own_sites' => TenantStarterPackType::OwnSites->value,
            'fleet' => TenantStarterPackType::Fleet->value,
            default => '',
        };
        $this->starterPackSize = '';
        $this->resetValidation(['starterPackType', 'starterPackSize']);
    }

    public function updatedStarterPackType(): void
    {
        $type = TenantStarterPackType::tryFrom($this->starterPackType);
        if ($type === null || ! $type->asksCompanySize()) {
            $this->starterPackSize = '';
        }
        $this->resetValidation('starterPackSize');
    }

    public function applyStarterPack(ApplyTenantStarterPackAction $apply): void
    {
        $user = $this->starterPackActor();
        $tenant = $this->starterPackTenant();
        $this->authorize('applyStarterPack', $tenant);

        $validated = $this->validate(
            ApplyTenantStarterPackRequest::ruleSet($this->starterPackType),
            ApplyTenantStarterPackRequest::messageSet(),
        );

        $apply->handle(
            $tenant,
            ApplyTenantStarterPackData::fromValidated($validated, $user->locale),
            $user,
        );

        $user->unsetRelation('tenant');
        $this->starterPackIntent = '';
        $this->starterPackType = '';
        $this->starterPackSize = '';
        $this->skipStarterPack = false;
        $this->resetValidation();
    }

    public function openRemoveStarterPackModal(): void
    {
        $this->authorize('removeStarterPack', $this->starterPackTenant());

        $this->resetValidation();
        $this->showRemoveStarterPackModal = true;
    }

    public function closeRemoveStarterPackModal(): void
    {
        $this->showRemoveStarterPackModal = false;
        $this->resetValidation();
    }

    public function removeStarterPack(RemoveTenantStarterPackAction $remove): void
    {
        $user = $this->starterPackActor();
        $tenant = $this->starterPackTenant();
        $this->authorize('removeStarterPack', $tenant);

        $remove->handle($tenant, $user);

        $user->unsetRelation('tenant');
        $this->closeRemoveStarterPackModal();
    }

    public function decidePhoneLogin(bool $enabled, DecideTenantPhoneLoginAction $decide): void
    {
        $tenant = $this->resolveTenant();
        $user = auth()->user();
        if (! $tenant instanceof Tenant || ! $user instanceof User) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);
        $decide->handle($tenant, $enabled, (int) $user->id);
    }

    public function dismissStarterPackResult(DismissTenantStarterPackResultAction $dismiss): void
    {
        $user = $this->starterPackActor();
        $tenant = $this->starterPackTenant();
        $this->authorize('dismissStarterPackResult', $tenant);

        $dismiss->handle($tenant, $user);

        $user->unsetRelation('tenant');
    }

    public function render(
        RealignSubscriptionPeriodAction $realign,
        ApplyPlanEntitlementsAction $applyEntitlements,
        BuildDashboardStatsAction $buildStats,
        BuildDashboardIntentHubAction $buildIntentHub,
        ListDashboardRecentIssuesAction $listRecentIssues,
        BuildCheckmateDashboardDataAction $buildCheckmate,
        ShouldShowWhatsNewCardAction $shouldShowWhatsNew,
        CountExpiringAbsencePhotosAction $countExpiringAbsencePhotos,
        ListTimeArchiveNoticesAction $listTimeArchiveNotices,
        ListSenseAttentionAction $listSenseAttention,
        ListSecurityAlertsAction $listSecurityAlerts,
    ) {
        $tenant = $this->resolveTenant();
        if ($tenant !== null) {
            $tenant = $realign->handle($tenant);
            if ($tenant->isTrialActive() && ! $tenant->hasTimeModule() && ! $tenant->checkmateMode()) {
                $tenant = $applyEntitlements->handle($tenant);
            }
        }

        $tenantId = (int) (Tenancy::id() ?? $tenant?->id ?? 0);
        $hasTimeModule = $tenant?->hasTimeModule() ?? false;
        $expiringAbsencePhotos = 0;
        $hasIotModule = $tenant?->hasIotModule() ?? false;
        // Checkmate: eigen dashboard-variant — geen facility-onboarding,
        // geen meldingen/taken-KPI's (docs/CHECKMATE.md §5).
        $isCheckmate = CheckmateMode::isActive($tenant);
        $checkmateData = $isCheckmate && $tenant !== null
            ? $buildCheckmate->handle($tenant)
            : null;
        $onboarding = $isCheckmate ? null : TenantOnboardingState::current();
        $user = auth()->user();
        $checkmateTiles = $checkmateData?->partitionTiles(
            $user instanceof User ? ($user->dashboard_hidden_checkmate_tiles ?? []) : [],
        ) ?? ['quickTiles' => [], 'kpis' => [], 'hidden' => []];
        $timeArchiveNotices = ['year' => null, 'photo_until' => null];
        if ($tenantId > 0) {
            $expiringAbsencePhotos = $countExpiringAbsencePhotos->handle(
                $tenantId,
                $user instanceof User ? $user->accessibleLocationIds() : null,
            );
            if ($tenant?->hasTimeModule() || $tenant?->checkmateMode()) {
                $timeArchiveNotices = $listTimeArchiveNotices->handle($tenantId);
            }
        }
        $canManageStarterPack = $user instanceof User
            && $tenant !== null
            && ! $isCheckmate
            && $user->can('removeStarterPack', $tenant);
        $canDismissStarterPackResult = $user instanceof User
            && $tenant !== null
            && ! $isCheckmate
            && $user->can('dismissStarterPackResult', $tenant);
        $canApplyStarterPack = $onboarding !== null
            && $onboarding->canApplyStarterPack
            && ! ($tenant?->hasStarterPack() ?? false)
            && $user instanceof User
            && $tenant !== null
            && $user->can('applyStarterPack', $tenant);
        $showStarterPackChooser = $canApplyStarterPack && ! $this->skipStarterPack;

        $starterPackType = TenantStarterPackType::tryFrom($this->starterPackType);
        $starterPackSize = TenantStarterPackSize::tryFrom($this->starterPackSize);
        $starterPackAsksSize = $starterPackType?->asksCompanySize() ?? false;
        $starterPackPreview = $starterPackType instanceof TenantStarterPackType
            && (! $starterPackAsksSize || $starterPackSize instanceof TenantStarterPackSize)
            ? TenantStarterPackCatalog::preview(
                $starterPackType,
                LocaleSupport::normalize($user?->locale ?? app()->getLocale()),
                $starterPackSize,
            )
            : null;

        $showIssuesTasksUi = $tenant?->workMenuIssuesTasksEnabled() ?? true;
        $stats = $isCheckmate ? null : $buildStats->handle($tenantId, $hasTimeModule, $hasIotModule, $showIssuesTasksUi, $user?->accessibleLocationIds());
        $kpiSplit = $stats?->partitionKpiTiles($user instanceof User ? ($user->dashboard_hidden_kpis ?? []) : [])
            ?? ['visible' => [], 'hidden' => []];
        $recent = $isCheckmate || ! $showIssuesTasksUi ? collect() : $listRecentIssues->handle($tenantId);
        $starterPackSummary = ! $isCheckmate
            && $tenant !== null
            && $tenant->shouldShowStarterPackResultCard()
            ? TenantStarterPackSummary::for($tenant)
            : null;
        $intentHub = ! $isCheckmate && $tenant !== null && $user instanceof User
            ? $buildIntentHub->handle($tenant, $user)
            : null;

        $manualClockInOptions = $this->showManualClockInModal
            ? $this->manualClockInFormOptions($user instanceof User ? $user->accessibleLocationIds() : null)
            : ['workers' => collect(), 'clockPoints' => collect()];

        $checkmateQrClockPoint = $this->showCheckmateClockPointQrModal && $this->checkmateQrClockPointId !== null
            ? ClockPoint::query()
                ->where('tenant_id', $tenantId)
                ->find($this->checkmateQrClockPointId)
            : null;

        $checkmatePwaVideoRel = 'video/checkmate_PWA.mp4';
        $checkmatePwaVideoUrl = $isCheckmate && app()->getLocale() === 'nl' && is_file(public_path($checkmatePwaVideoRel))
            ? asset($checkmatePwaVideoRel)
            : null;

        $securityAlerts = [];
        $showPhoneLoginPrompt = false;
        if ($user instanceof User && $tenant !== null && $user->can('manageOrganisation', $tenant)) {
            $securityAlerts = $listSecurityAlerts->handle((int) $tenant->id);
            $showPhoneLoginPrompt = $tenant->backoffice_phone_login_decided_at === null;
        }

        $senseBoard = (! $isCheckmate && $user instanceof User && $user->can('viewAny', SensePoint::class))
            ? $listSenseAttention->handle($tenantId)
            : new SenseAttentionBoard([], null);

        return view('livewire.dashboard', [
            'checkmate' => $checkmateData,
            'checkmateQuickTiles' => $checkmateTiles['quickTiles'],
            'checkmateKpis' => $checkmateTiles['kpis'],
            'hiddenCheckmateTiles' => $checkmateTiles['hidden'],
            'checkmatePwaVideoUrl' => $checkmatePwaVideoUrl,
            'checkmateQrClockPoint' => $checkmateQrClockPoint,
            'checkmateQrPackTemplates' => QrStickerSheetTemplate::printableDownloadCases(),
            'stats' => $stats,
            'visibleKpis' => $kpiSplit['visible'],
            'hiddenKpis' => $kpiSplit['hidden'],
            'recent' => $recent,
            'showIssuesTasksUi' => $showIssuesTasksUi,
            'intentHub' => $intentHub,
            'manualClockInWorkers' => $manualClockInOptions['workers'],
            'manualClockInClockPoints' => $manualClockInOptions['clockPoints'],
            'showWhatsNew' => $user instanceof User && $shouldShowWhatsNew->handle($user),
            'securityAlerts' => $securityAlerts,
            'securityAlertTenantName' => (string) ($tenant?->name ?? ''),
            'showPhoneLoginPrompt' => $showPhoneLoginPrompt,
            'portalBatteryState' => $tenant?->portalDashboardBatteryState(),
            'onboarding' => $onboarding,
            'hasTimeModule' => $hasTimeModule,
            'expiringAbsencePhotos' => $expiringAbsencePhotos,
            'timeArchiveYear' => $timeArchiveNotices['year'],
            'timeArchivePhotoUntil' => $timeArchiveNotices['photo_until'],
            'canOpenSubscription' => $user instanceof User && $tenant !== null && $user->can('manageSubscription', $tenant),
            'canApplyStarterPack' => $canApplyStarterPack,
            'showStarterPackChooser' => $showStarterPackChooser,
            'canManageStarterPack' => $canManageStarterPack,
            'canDismissStarterPackResult' => $canDismissStarterPackResult,
            'starterPackSummary' => $starterPackSummary,
            'starterPackTypes' => TenantStarterPackType::onboardingChoices(),
            'starterPackAtCustomerTypes' => TenantStarterPackType::atCustomerFollowUpChoices(),
            'starterPackSizes' => TenantStarterPackSize::cases(),
            'starterPackAsksSize' => $starterPackAsksSize,
            'starterPackPreview' => $starterPackPreview,
            'starterPackUnitsHref' => $starterPackSummary !== null
                ? TenantOnboardingState::unitsOnboardingHref()
                : null,
            'senseAttention' => $senseBoard->lines,
            'senseReadingsUrl' => $senseBoard->readingsUrl,
            'canAddDevice' => $user instanceof User && $user->can('pairDeviceAny', Location::class),
        ]);
    }

    private function starterPackActor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function starterPackTenant(): Tenant
    {
        $tenant = $this->resolveTenant();
        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }

    private function abortUnlessCheckmate(): User
    {
        $tenant = $this->resolveTenant();
        $user = auth()->user();
        abort_unless($tenant instanceof Tenant && CheckmateMode::isActive($tenant), 403);
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function resolveTenant(): ?Tenant
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        if ($user->is_superuser && SupportTenantContext::isActive()) {
            return Tenant::query()->find(SupportTenantContext::activeTenantId());
        }

        return $user->tenant;
    }
}
