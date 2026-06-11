<?php

namespace App\Models;

use App\Support\Qr\QrCenterLogo;
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
        'qr_sticker_avery_62x89_header_text',
        'custom_theme_active',
        'custom_theme_bg',
        'custom_theme_btn',
        'trial_ends_at',
        'billing_plan',
        'billing_active_until',
        'is_active',
        'stripe_customer_id',
        'allow_trial_api',
    ];

    protected function casts(): array
    {
        return [
            'custom_theme_active' => 'boolean',
            'trial_ends_at' => 'datetime',
            'billing_active_until' => 'datetime',
            'is_active' => 'boolean',
            'allow_trial_api' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
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

        // Only Business and Enterprise plans have API access
        $apiAllowedPlans = ['business', 'enterprise'];
        
        return in_array($this->billing_plan, $apiAllowedPlans, true);
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
        if (! is_string($planKey) || $planKey === '') {
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

    public function currentUsersCount(): int
    {
        return $this->users()
            ->where('is_superuser', false)
            ->where('role', '!=', User::ROLE_ADMIN)
            ->count();
    }

    public function effectivePlanKey(): ?string
    {
        if ($this->isPaidSubscriptionActive() || $this->isInPaidSubscriptionGrace()) {
            return $this->billing_plan;
        }

        if ($this->isTrialActive()) {
            return config('billing.trial_plan_facility');
        }

        return null;
    }

    /** null = onbeperkt (legacy of enterprise). */
    public function maxUnitsLimit(): ?int
    {
        if ($this->isLegacyWithoutBillingTracking()) {
            return null;
        }

        $planKey = $this->effectivePlanKey();
        if ($planKey === null) {
            return null;
        }

        $max = config("billing.plans.{$planKey}.units_limit");

        return is_int($max) ? $max : (is_numeric($max) ? (int) $max : null);
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

    /** null = onbeperkt (legacy of enterprise). */
    public function maxUsersLimit(): ?int
    {
        if ($this->isLegacyWithoutBillingTracking()) {
            return null;
        }

        $planKey = $this->effectivePlanKey();
        if ($planKey === null) {
            return null;
        }

        $max = config("billing.plans.{$planKey}.users_limit");

        return is_int($max) ? $max : (is_numeric($max) ? (int) $max : null);
    }

    public function remainingUserSlots(): ?int
    {
        $max = $this->maxUsersLimit();
        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->currentUsersCount());
    }

    public function isAtUnitLimit(): bool
    {
        $remaining = $this->remainingUnitSlots();

        return $remaining !== null && $remaining === 0;
    }

    public function canAddUser(): bool
    {
        $remaining = $this->remainingUserSlots();

        return $remaining === null || $remaining > 0;
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
    public function userLimitWarning(): ?string
    {
        return $this->limitWarningLevel(
            $this->remainingUserSlots(),
            $this->maxUsersLimit(),
        );
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

    public function assertCanAddUsers(int $count): void
    {
        $remaining = $this->remainingUserSlots();
        if ($remaining === null) {
            return;
        }

        if ($count > $remaining) {
            throw new \InvalidArgumentException('user_limit_exceeded');
        }
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
        $path = $this->portal_background_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
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
}
