<?php

namespace App\Models;

use App\Enums\ReservationLifecycle;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use BelongsToTenant, HasFactory;

    public const HOLD_MINUTES = 30;

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'worker_id',
        'created_by_user_id',
        'guest_first_name',
        'guest_last_name',
        'guest_email',
        'start_at',
        'end_at',
        'expires_at',
        'confirmed_at',
        'cancelled_at',
        'confirm_token',
        'manage_token',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function guestFullName(): string
    {
        return trim($this->guest_first_name.' '.$this->guest_last_name);
    }

    public function lifecycle(): ReservationLifecycle
    {
        if ($this->cancelled_at !== null) {
            return ReservationLifecycle::Cancelled;
        }

        if ($this->confirmed_at !== null) {
            return ReservationLifecycle::Confirmed;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return ReservationLifecycle::Expired;
        }

        return ReservationLifecycle::Pending;
    }

    public function isPendingActive(): bool
    {
        return $this->lifecycle() === ReservationLifecycle::Pending;
    }

    public function isConfirmed(): bool
    {
        return $this->lifecycle() === ReservationLifecycle::Confirmed;
    }

    public function isCancellable(): bool
    {
        return in_array($this->lifecycle(), [
            ReservationLifecycle::Pending,
            ReservationLifecycle::Confirmed,
        ], true);
    }

    public function isEditable(): bool
    {
        return $this->isCancellable() && $this->end_at->isFuture();
    }

    /**
     * Blocks the slot: confirmed, or pending within the 30-minute hold.
     *
     * @param  Builder<Reservation>  $query
     * @return Builder<Reservation>
     */
    public function scopeBlocking(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->whereNull('cancelled_at')
            ->where(function (Builder $inner) use ($at) {
                $inner->whereNotNull('confirmed_at')
                    ->orWhere(function (Builder $pending) use ($at) {
                        $pending->whereNull('confirmed_at')
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>', $at);
                    });
            });
    }

    /**
     * @param  Builder<Reservation>  $query
     * @return Builder<Reservation>
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): Builder
    {
        return $query
            ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start);
    }
}
