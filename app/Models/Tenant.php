<?php

namespace App\Models;

use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\QrStickerSheetTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'street',
        'house_number',
        'postal_code',
        'city',
        'country_code',
        'logo_path',
        'portal_background_path',
        'custom_theme_active',
        'custom_theme_bg',
        'custom_theme_btn',
        'trial_ends_at',
        'billing_plan',
        'billing_active_until',
        'billing_units_cap',
        'is_active',
        'stripe_customer_id',
        'allow_trial_api',
        'has_esg_module',
        'has_iot_module',
        'time_qr_rotation_months',
        'has_time_module',
        'time_require_worker_pin',
        'time_gps_on_clock',
        'enterprise_number',
        'foreign_vat_number',
        'presence_compliance_enabled',
        'presence_compliance_scope',
        'presence_rsz_client_id',
        'presence_rsz_private_key',
        'work_menu_calendar_enabled',
        'work_menu_reservations_enabled',
        'work_menu_inspection_rounds_enabled',
        'work_menu_unit_measurements_enabled',
        'starter_pack_key',
        'starter_pack_applied_at',
        'starter_pack_payload',
        'starter_pack_result_dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_theme_active' => 'boolean',
            'trial_ends_at' => 'datetime',
            'billing_active_until' => 'datetime',
            'is_active' => 'boolean',
            'allow_trial_api' => 'boolean',
            'has_esg_module' => 'boolean',
            'has_iot_module' => 'boolean',
            'has_time_module' => 'boolean',
            'time_require_worker_pin' => 'boolean',
            'time_gps_on_clock' => 'boolean',
            'presence_compliance_enabled' => 'boolean',
            'presence_rsz_client_id' => 'encrypted',
            'presence_rsz_private_key' => 'encrypted',
            'work_menu_calendar_enabled' => 'boolean',
            'work_menu_reservations_enabled' => 'boolean',
            'work_menu_inspection_rounds_enabled' => 'boolean',
            'work_menu_unit_measurements_enabled' => 'boolean',
            'starter_pack_applied_at' => 'datetime',
            'starter_pack_payload' => 'array',
            'starter_pack_result_dismissed_at' => 'datetime',
        ];
    }

    public function hasStarterPack(): bool
    {
        return filled($this->starter_pack_key);
    }

    public function shouldShowStarterPackResultCard(): bool
    {
        if (! $this->hasStarterPack() || $this->starter_pack_applied_at === null) {
            return false;
        }

        if ($this->starter_pack_result_dismissed_at !== null) {
            return false;
        }

        $days = max(1, (int) config('onboarding.starter_pack_result_visible_days', 7));

        return $this->starter_pack_applied_at->gte(now()->subDays($days));
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function qrStickerSheetSettings(): HasMany
    {
        return $this->hasMany(TenantQrStickerSheetSetting::class);
    }

    public function qrStickerSheetSetting(QrStickerSheetTemplate $template): ?TenantQrStickerSheetSetting
    {
        if ($this->relationLoaded('qrStickerSheetSettings')) {
            return $this->qrStickerSheetSettings
                ->firstWhere('template', $template->value);
        }

        return $this->qrStickerSheetSettings()
            ->where('template', $template->value)
            ->first();
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function internalTeams(): HasMany
    {
        return $this->hasMany(InternalTeam::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function isLegacyWithoutBillingTracking(): bool
    {
        return $this->trial_ends_at === null
            && $this->billing_plan === null
            && $this->billing_active_until === null;
    }

    public function isTrialActive(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * Trial-self-service purge alleen bij actieve proef zonder betaald/grace.
     * Legacy en betalende tenants: betaald-spoor (superuser-uitvoering).
     */
    public function purgeTrack(): \App\Enums\TenantPurgeTrack
    {
        if ($this->isTrialActive()
            && ! $this->isPaidSubscriptionActive()
            && ! $this->isInPaidSubscriptionGrace()) {
            return \App\Enums\TenantPurgeTrack::Trial;
        }

        return \App\Enums\TenantPurgeTrack::Paid;
    }

    /**
     * Proef verlopen, nooit een plan geactiveerd → kandidaat voor auto-purge.
     */
    public function isExpiredTrialWithoutSubscription(): bool
    {
        if ($this->isLegacyWithoutBillingTracking()) {
            return false;
        }

        if ($this->billing_plan !== null) {
            return false;
        }

        if ($this->isPaidSubscriptionActive() || $this->isInPaidSubscriptionGrace()) {
            return false;
        }

        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    public function isPaidSubscriptionActive(): bool
    {
        return $this->billing_plan !== null
            && $this->billing_active_until !== null
            && $this->billing_active_until->isFuture();
    }

    public function isInPaidSubscriptionGrace(): bool
    {
        if ($this->billing_plan === null || $this->billing_active_until === null) {
            return false;
        }

        if ($this->billing_active_until->isFuture()) {
            return false;
        }

        $graceEnd = $this->billing_active_until->copy()
            ->addDays((int) config('billing.paid_expiry_grace_days', 7));

        return $graceEnd->isFuture();
    }

    public function hasFullAppAccess(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isLegacyWithoutBillingTracking()) {
            return true;
        }

        return $this->isTrialActive()
            || $this->isPaidSubscriptionActive()
            || $this->isInPaidSubscriptionGrace();
    }

    public function hasApiAccess(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isLegacyWithoutBillingTracking()) {
            return true;
        }

        if ($this->isTrialActive() && $this->allow_trial_api) {
            return true;
        }

        if (! $this->isPaidSubscriptionActive() && ! $this->isInPaidSubscriptionGrace()) {
            return false;
        }

        // Only Corporate plan has API access
        return self::normalizeBillingPlanKey($this->billing_plan) === 'corporate';
    }

    public function hasCsvWorkersImport(): bool
    {
        return $this->planAllows('csv_workers_import');
    }

    public function hasCsvUnitsImport(): bool
    {
        return $this->planAllows('csv_units_import');
    }

    public function planAllows(string $feature): bool
    {
        if ($this->isLegacyWithoutBillingTracking()) {
            return true;
        }

        $config = $this->planConfig();
        if ($config === null) {
            return false;
        }

        return (bool) ($config[$feature] ?? false);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function planConfig(): ?array
    {
        $planKey = $this->effectivePlanKey();
        if ($planKey === null) {
            return null;
        }

        if ($planKey === config('billing.trial_plan_facility')) {
            return config('billing.trial');
        }

        return config("billing.plans.{$planKey}");
    }

    public function hasEsgModule(): bool
    {
        return (bool) $this->has_esg_module;
    }

    public function hasIotModule(): bool
    {
        return (bool) $this->has_iot_module;
    }

    public function hasTimeModule(): bool
    {
        return (bool) $this->has_time_module;
    }

    public function requiresWorkerPin(): bool
    {
        return $this->hasTimeModule() && (bool) $this->time_require_worker_pin;
    }

    public function requestsClockGps(): bool
    {
        return $this->hasTimeModule() && (bool) $this->time_gps_on_clock;
    }

    public function presenceComplianceEnabled(): bool
    {
        return $this->hasTimeModule() && (bool) $this->presence_compliance_enabled;
    }

    public function presenceComplianceScope(): ?\App\Enums\PresenceComplianceScope
    {
        $raw = $this->presence_compliance_scope;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return \App\Enums\PresenceComplianceScope::tryFrom($raw);
    }

    public function workMenuCalendarEnabled(): bool
    {
        return (bool) ($this->work_menu_calendar_enabled ?? true);
    }

    public function workMenuReservationsEnabled(): bool
    {
        return (bool) ($this->work_menu_reservations_enabled ?? true);
    }

    public function workMenuInspectionRoundsEnabled(): bool
    {
        return (bool) ($this->work_menu_inspection_rounds_enabled ?? true);
    }

    public function workMenuUnitMeasurementsEnabled(): bool
    {
        return (bool) ($this->work_menu_unit_measurements_enabled ?? true);
    }

    public function trialDaysRemaining(): int
    {
        if (! $this->isTrialActive()) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
    }

    public static function subscriptionPeriodDaysForPlan(?string $planKey): int
    {
        $planKey = self::normalizeBillingPlanKey($planKey);
        if ($planKey === null) {
            return max(1, (int) config('billing.subscription_period_days', 30));
        }

        $row = config("billing.plans.{$planKey}");
        if (is_array($row) && isset($row['subscription_period_days'])) {
            return max(1, (int) $row['subscription_period_days']);
        }

        return max(1, (int) config('billing.subscription_period_days', 30));
    }

    public function subscriptionPeriodDays(?string $planKey = null): int
    {
        return self::subscriptionPeriodDaysForPlan($planKey ?? $this->billing_plan ?? $this->effectivePlanKey());
    }

    public function needsBillingPeriodRealignment(): bool
    {
        if (! $this->isPaidSubscriptionActive() || $this->billing_active_until === null || $this->billing_plan === null) {
            return false;
        }

        $remaining = max(0, (int) now()->diffInDays($this->billing_active_until, false));

        return $remaining > $this->subscriptionPeriodDays();
    }

    public function paidSubscriptionGraceEndsAt(): ?Carbon
    {
        if ($this->billing_active_until === null) {
            return null;
        }

        $graceDays = max(1, (int) config('billing.paid_expiry_grace_days', 7));

        return $this->billing_active_until->copy()->addDays($graceDays);
    }

    /**
     * @return array{grace_days: int, days_remaining: int, blocks_remaining: int, ends_on: Carbon}|null
     */
    public function paidSubscriptionGraceBatteryState(): ?array
    {
        if (! $this->isInPaidSubscriptionGrace()) {
            return null;
        }

        $graceDays = max(1, (int) config('billing.paid_expiry_grace_days', 7));
        $endsOn = $this->paidSubscriptionGraceEndsAt();
        if ($endsOn === null) {
            return null;
        }

        $now = Carbon::now();
        $daysRemaining = max(0, (int) $now->diffInDays($endsOn, false));
        if ($daysRemaining === 0 && $now->lt($endsOn)) {
            $daysRemaining = 1;
        }

        $daysPerBlock = max(1, (int) ceil($graceDays / 5));
        $blocksRemaining = $daysRemaining > 0 ? (int) ceil($daysRemaining / $daysPerBlock) : 0;

        return [
            'grace_days' => $graceDays,
            'days_remaining' => $daysRemaining,
            'blocks_remaining' => $blocksRemaining,
            'ends_on' => $endsOn,
        ];
    }

    /**
     * @return array{trial_days: int, days_remaining: int, blocks_remaining: int, ends_on: Carbon}|null
     */
    public function trialBatteryState(): ?array
    {
        if ($this->trial_ends_at === null) {
            return null;
        }

        $trialDays = max(1, (int) config('billing.trial_days', 14));
        $endsOn = $this->trial_ends_at->copy();
        $startsOn = $endsOn->copy()->subDays($trialDays);
        $now = Carbon::now();

        $elapsedDays = $startsOn->diffInDays($now, false);
        $elapsedDays = max(0, min($trialDays, (int) $elapsedDays));

        $daysRemaining = max(0, $trialDays - $elapsedDays);
        $daysPerBlock = max(1, (int) ceil($trialDays / 5));
        $blocksRemaining = $daysRemaining > 0 ? (int) ceil($daysRemaining / $daysPerBlock) : 0;

        return [
            'trial_days' => $trialDays,
            'days_remaining' => $daysRemaining,
            'blocks_remaining' => $blocksRemaining,
            'ends_on' => $endsOn,
        ];
    }

    /**
     * @return array{period_days: int, days_remaining: int, blocks_remaining: int, ends_on: Carbon}|null
     */
    public function paidSubscriptionBatteryState(): ?array
    {
        if (! $this->isPaidSubscriptionActive() || $this->billing_active_until === null) {
            return null;
        }

        $periodDays = $this->subscriptionPeriodDays();
        $endsOn = $this->billing_active_until->copy();
        $now = Carbon::now();
        $daysRemaining = max(0, (int) $now->copy()->startOfDay()->diffInDays($endsOn->copy()->startOfDay(), false));
        if ($daysRemaining === 0 && $now->lt($endsOn)) {
            $daysRemaining = 1;
        }

        $daysPerBlock = max(1, (int) ceil($periodDays / 5));
        $blocksRemaining = $daysRemaining > 0 ? (int) ceil($daysRemaining / $daysPerBlock) : 0;

        return [
            'period_days' => $periodDays,
            'days_remaining' => $daysRemaining,
            'blocks_remaining' => $blocksRemaining,
            'ends_on' => $endsOn,
        ];
    }

    /**
     * @return array{type: 'trial'|'grace'|'paid', days_remaining: int, blocks_remaining: int, ends_on: Carbon, trial_days?: int, grace_days?: int, period_days?: int, plan?: string}|null
     */
    public function portalDashboardBatteryState(): ?array
    {
        if ($this->isLegacyWithoutBillingTracking()) {
            return null;
        }

        if ($this->isPaidSubscriptionActive()) {
            $paid = $this->paidSubscriptionBatteryState();
            if ($paid === null) {
                return null;
            }

            return [
                'type' => 'paid',
                'days_remaining' => $paid['days_remaining'],
                'blocks_remaining' => $paid['blocks_remaining'],
                'ends_on' => $paid['ends_on'],
                'period_days' => $paid['period_days'],
                'plan' => $this->billing_plan,
            ];
        }

        if ($this->isInPaidSubscriptionGrace()) {
            $grace = $this->paidSubscriptionGraceBatteryState();
            if ($grace === null) {
                return null;
            }

            return [
                'type' => 'grace',
                'days_remaining' => $grace['days_remaining'],
                'blocks_remaining' => $grace['blocks_remaining'],
                'ends_on' => $grace['ends_on'],
                'grace_days' => $grace['grace_days'],
            ];
        }

        if ($this->isTrialActive()) {
            $trial = $this->trialBatteryState();
            if ($trial === null) {
                return null;
            }

            return [
                'type' => 'trial',
                'days_remaining' => $trial['days_remaining'],
                'blocks_remaining' => $trial['blocks_remaining'],
                'ends_on' => $trial['ends_on'],
                'trial_days' => $trial['trial_days'],
            ];
        }

        return null;
    }

    public function currentUnitsCount(): int
    {
        return Unit::query()->withoutGlobalScopes()->where('tenant_id', $this->id)->count();
    }

    public function currentSeatsCount(): int
    {
        $users = $this->users()
            ->where('is_superuser', false)
            ->where('is_active', true)
            ->count();

        $workers = Worker::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->id)
            ->where('is_active', true)
            ->whereNull('user_id')
            ->count();

        return $users + $workers;
    }

    /** @deprecated Gebruik {@see currentSeatsCount()} — telt collega's (admin + medewerker) + workers. */
    public function currentUsersCount(): int
    {
        return $this->currentSeatsCount();
    }

    public function effectivePlanKey(): ?string
    {
        if ($this->isPaidSubscriptionActive() || $this->isInPaidSubscriptionGrace()) {
            return self::normalizeBillingPlanKey($this->billing_plan);
        }

        if ($this->isTrialActive()) {
            return config('billing.trial_plan_facility');
        }

        return null;
    }

    /**
     * Plan-keys in config zijn lowercase; DB kan legacy casing hebben (bv. "Facility").
     */
    public static function normalizeBillingPlanKey(?string $planKey): ?string
    {
        if (! is_string($planKey) || $planKey === '') {
            return null;
        }

        return strtolower(trim($planKey));
    }

    /** null = onbeperkt (legacy of enterprise). */
    public function maxUnitsLimit(): ?int
    {
        return $this->billingLimitValue('units_limit');
    }

    public function remainingUnitSlots(): ?int
    {
        $max = $this->maxUnitsLimit();
        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->currentUnitsCount());
    }

    public function assertCanAddUnits(int $count): void
    {
        $remaining = $this->remainingUnitSlots();
        if ($remaining === null) {
            return;
        }

        if ($count > $remaining) {
            throw new \InvalidArgumentException('unit_limit_exceeded');
        }
    }

    /** null = onbeperkt (legacy of corporate). */
    public function maxLocationsLimit(): ?int
    {
        return $this->billingLimitValue('locations_limit');
    }

    public function currentLocationsCount(): int
    {
        return Location::query()->withoutGlobalScopes()->where('tenant_id', $this->id)->count();
    }

    public function remainingLocationSlots(): ?int
    {
        $max = $this->maxLocationsLimit();
        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->currentLocationsCount());
    }

    public function assertCanAddLocations(int $count): void
    {
        $remaining = $this->remainingLocationSlots();
        if ($remaining === null) {
            return;
        }

        if ($count > $remaining) {
            throw new \InvalidArgumentException('location_limit_exceeded');
        }
    }

    public function isAtLocationLimit(): bool
    {
        $remaining = $this->remainingLocationSlots();

        return $remaining !== null && $remaining === 0;
    }

    /** null = onbeperkt (legacy of enterprise). */
    public function maxSeatsLimit(): ?int
    {
        return $this->billingLimitValue('seats_limit');
    }

    /** @deprecated Gebruik {@see maxSeatsLimit()}. */
    public function maxUsersLimit(): ?int
    {
        return $this->maxSeatsLimit();
    }

    /** null = onbeperkt (legacy of enterprise). */
    public function maxAnnouncementsPerUnitLimit(): ?int
    {
        return $this->billingLimitValue('announcements_per_unit');
    }

    /** null = onbeperkt (legacy of enterprise). */
    public function maxDocumentsPerUnitLimit(): ?int
    {
        return $this->billingLimitValue('documents_per_unit');
    }

    /** null = onbeperkt (legacy of corporate). */
    public function maxPhotosOrgLimit(): ?int
    {
        return $this->billingLimitValue('photos_org_limit');
    }

    public function currentPhotosCount(): int
    {
        return IssuePhoto::query()->withoutGlobalScopes()->where('tenant_id', $this->id)->count();
    }

    public function remainingPhotoOrgSlots(): ?int
    {
        $max = $this->maxPhotosOrgLimit();
        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->currentPhotosCount());
    }

    public function isAtPhotosOrgLimit(): bool
    {
        $remaining = $this->remainingPhotoOrgSlots();

        return $remaining !== null && $remaining === 0;
    }

    public function assertCanAddPhotos(int $count = 1): void
    {
        $max = $this->maxPhotosOrgLimit();
        if ($max === null) {
            return;
        }

        if ($this->currentPhotosCount() + $count > $max) {
            throw new \InvalidArgumentException('photo_org_limit_exceeded');
        }
    }

    /** null = onbeperkt (legacy of corporate). */
    public function maxDocumentsOrgLimit(): ?int
    {
        return $this->billingLimitValue('documents_org_limit');
    }

    public function currentDocumentsCount(): int
    {
        return Document::query()->withoutGlobalScopes()->where('tenant_id', $this->id)->count();
    }

    public function currentDocumentsCountForUnit(int $unitId, ?int $excludeDocumentId = null): int
    {
        $query = Document::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->id)
            ->where('unit_id', $unitId);

        if ($excludeDocumentId !== null) {
            $query->whereKeyNot($excludeDocumentId);
        }

        return $query->count();
    }

    public function currentActiveAnnouncementsCountForUnit(int $unitId, ?int $excludeAnnouncementId = null): int
    {
        $query = Announcement::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->id)
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));

        if ($excludeAnnouncementId !== null) {
            $query->whereKeyNot($excludeAnnouncementId);
        }

        return $query->count();
    }

    public function remainingDocumentOrgSlots(): ?int
    {
        $max = $this->maxDocumentsOrgLimit();
        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->currentDocumentsCount());
    }

    public function isAtDocumentsOrgLimit(): bool
    {
        $remaining = $this->remainingDocumentOrgSlots();

        return $remaining !== null && $remaining === 0;
    }

    public function assertCanAddDocument(?int $unitId): void
    {
        $orgMax = $this->maxDocumentsOrgLimit();
        if ($orgMax !== null && $this->currentDocumentsCount() >= $orgMax) {
            throw new \InvalidArgumentException('document_org_limit_exceeded');
        }

        if ($unitId === null) {
            return;
        }

        $unitMax = $this->maxDocumentsPerUnitLimit();
        if ($unitMax === null) {
            return;
        }

        if ($this->currentDocumentsCountForUnit($unitId) >= $unitMax) {
            throw new \InvalidArgumentException('document_unit_limit_exceeded');
        }
    }

    public function assertCanAssignDocumentToUnit(int $unitId, ?int $excludeDocumentId = null): void
    {
        $unitMax = $this->maxDocumentsPerUnitLimit();
        if ($unitMax === null) {
            return;
        }

        if ($this->currentDocumentsCountForUnit($unitId, $excludeDocumentId) >= $unitMax) {
            throw new \InvalidArgumentException('document_unit_limit_exceeded');
        }
    }

    public function assertCanActivateAnnouncement(?int $unitId, ?int $excludeAnnouncementId = null): void
    {
        if ($unitId === null) {
            return;
        }

        $unitMax = $this->maxAnnouncementsPerUnitLimit();
        if ($unitMax === null) {
            return;
        }

        if ($this->currentActiveAnnouncementsCountForUnit($unitId, $excludeAnnouncementId) >= $unitMax) {
            throw new \InvalidArgumentException('announcement_unit_limit_exceeded');
        }
    }

    private function billingLimitValue(string $field): ?int
    {
        if ($this->isLegacyWithoutBillingTracking()) {
            return null;
        }

        $planKey = $this->effectivePlanKey();
        if ($planKey === null) {
            return null;
        }

        if ($planKey === 'corporate') {
            if (in_array($field, ['units_limit', 'documents_org_limit'], true)) {
                $cap = $this->billing_units_cap;

                return is_int($cap) ? $cap : (is_numeric($cap) ? (int) $cap : null);
            }

            return null;
        }

        $configKey = $planKey === config('billing.trial_plan_facility')
            ? "billing.trial.{$field}"
            : "billing.plans.{$planKey}.{$field}";

        $max = config($configKey);

        return is_int($max) ? $max : (is_numeric($max) ? (int) $max : null);
    }

    public function remainingSeatSlots(): ?int
    {
        $max = $this->maxSeatsLimit();
        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->currentSeatsCount());
    }

    /** @deprecated Gebruik {@see remainingSeatSlots()}. */
    public function remainingUserSlots(): ?int
    {
        return $this->remainingSeatSlots();
    }

    public function isAtUnitLimit(): bool
    {
        $remaining = $this->remainingUnitSlots();

        return $remaining !== null && $remaining === 0;
    }

    public function canAddSeat(): bool
    {
        $remaining = $this->remainingSeatSlots();

        return $remaining === null || $remaining > 0;
    }

    /** @deprecated Gebruik {@see canAddSeat()}. */
    public function canAddUser(): bool
    {
        return $this->canAddSeat();
    }

    /** null = geen limiet of ruim voldoende; warning | critical */
    public function unitLimitWarning(): ?string
    {
        return $this->limitWarningLevel(
            $this->remainingUnitSlots(),
            $this->maxUnitsLimit(),
        );
    }

    /** null = geen limiet of ruim voldoende; warning | critical */
    public function seatLimitWarning(): ?string
    {
        return $this->limitWarningLevel(
            $this->remainingSeatSlots(),
            $this->maxSeatsLimit(),
        );
    }

    /** @deprecated Gebruik {@see seatLimitWarning()}. */
    public function userLimitWarning(): ?string
    {
        return $this->seatLimitWarning();
    }

    private function limitWarningLevel(?int $remaining, ?int $max): ?string
    {
        if ($remaining === null || $max === null) {
            return null;
        }

        if ($remaining === 0) {
            return 'critical';
        }

        $threshold = max(2, (int) floor($max * 0.15));

        if ($remaining <= $threshold) {
            return 'warning';
        }

        return null;
    }

    public function assertCanAddSeats(int $count): void
    {
        $remaining = $this->remainingSeatSlots();
        if ($remaining === null) {
            return;
        }

        if ($count > $remaining) {
            throw new \InvalidArgumentException('seat_limit_exceeded');
        }
    }

    /** @deprecated Gebruik {@see assertCanAddSeats()}. */
    public function assertCanAddUsers(int $count): void
    {
        $this->assertCanAddSeats($count);
    }

    /** Absoluut pad voor QR-centrelogo (organisatie of WinProx-fallback). */
    public function centerLogoAbsolutePath(): string
    {
        return QrCenterLogo::absolutePath($this);
    }

    /** Publieke URL van het organisatielogo (instellingen), of null. */
    public function logoPublicUrl(): ?string
    {
        return QrCenterLogo::tenantLogoPublicUrl($this);
    }

    /** Publieke URL van het portaal-achtergrond, of null. */
    public function portalBackgroundPublicUrl(): ?string
    {
        return \App\Support\TenantPortalBackground::publicUrl($this->portal_background_path);
    }

    public function organisationAddressLine(): ?string
    {
        $streetLine = trim(implode(' ', array_filter([
            trim((string) ($this->street ?? '')),
            trim((string) ($this->house_number ?? '')),
        ], fn (string $part) => $part !== '')));

        $localityLine = trim(implode(' ', array_filter([
            trim((string) ($this->country_code ?? '')),
            trim((string) ($this->postal_code ?? '')),
            trim((string) ($this->city ?? '')),
        ], fn (string $part) => $part !== '')));

        $line = match (true) {
            $streetLine !== '' && $localityLine !== '' => $streetLine.', '.$localityLine,
            $streetLine !== '' => $streetLine,
            $localityLine !== '' => $localityLine,
            default => '',
        };

        return $line !== '' ? $line : null;
    }

    public function effectiveTimeQrRotationMonths(): ?int
    {
        if ($this->time_qr_rotation_months !== null) {
            $months = (int) $this->time_qr_rotation_months;

            return $months > 0 ? $months : null;
        }

        $default = (int) config('time.qr_rotation_months_default', 6);

        return $default > 0 ? $default : null;
    }
}
