<?php

namespace App\Models;

use App\Support\Qr\QrCodePngWriter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo_path',
        'trial_ends_at',
        'billing_plan',
        'billing_active_until',
        'is_active',
        'stripe_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'billing_active_until' => 'datetime',
            'is_active' => 'boolean',
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

    public function trialDaysRemaining(): int
    {
        if (! $this->isTrialActive()) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
    }

    public function currentUnitsCount(): int
    {
        return Unit::query()->withoutGlobalScopes()->where('tenant_id', $this->id)->count();
    }

    public function currentUsersCount(): int
    {
        return $this->users()->where('is_superuser', false)->count();
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
        if (is_string($this->logo_path) && $this->logo_path !== '') {
            $absolute = Storage::disk('public')->path($this->logo_path);
            if (is_file($absolute)) {
                return $absolute;
            }
        }

        return QrCodePngWriter::winproxLogoPath();
    }
}
