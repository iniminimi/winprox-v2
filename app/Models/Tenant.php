<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
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
}
