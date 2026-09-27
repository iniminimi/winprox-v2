<?php

namespace App\Models;

use App\Enums\ClockDisplayClaimStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pending koppelaanvraag van een fysiek klokscherm (ESP32) aan een Clock Point.
 * Max. één actieve pending per punt: afgedwongen door unique index op
 * `pending_slot` (= clock_point_id zolang status=pending, anders NULL).
 * Geen generated column — MySQL staat geen FK op clock_point_id toe als die
 * in een generated-expressie zit; `saving` houdt pending_slot synchroon.
 */
class ClockDisplayClaim extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (ClockDisplayClaim $claim): void {
            $claim->pending_slot = $claim->status === ClockDisplayClaimStatus::Pending
                ? $claim->clock_point_id
                : null;
        });
    }

    protected $fillable = [
        'tenant_id',
        'clock_point_id',
        'claim_token',
        'pairing_code',
        'device_hint',
        'ip',
        'status',
        'denied_reason',
        'issued_token',
        'expires_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClockDisplayClaimStatus::class,
            'issued_token' => 'encrypted',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function clockPoint(): BelongsTo
    {
        return $this->belongsTo(ClockPoint::class);
    }

    /**
     * Eén definitie voor "actieve pending" — SubmitClaim-idempotentie,
     * ConfirmClaim-assert en de admin-lijst gebruiken allemaal deze predicate
     * (niet afhankelijk van de scheduled expired-cleanup voor correctheid).
     */
    public function isPending(): bool
    {
        return $this->status === ClockDisplayClaimStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function scopeActivePending(Builder $query): Builder
    {
        return $query->where('status', ClockDisplayClaimStatus::Pending->value)
            ->where('expires_at', '>', now());
    }
}
