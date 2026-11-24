<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScreenClaimStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreenClaim extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::saving(function (ScreenClaim $claim): void {
            $claim->pending_slot = $claim->status === ScreenClaimStatus::Pending
                ? $claim->screen_id
                : null;
        });
    }

    protected $fillable = [
        'tenant_id',
        'screen_id',
        'claim_token',
        'pairing_code',
        'device_hint',
        'ip',
        'status',
        'denied_reason',
        'issued_token',
        'expires_at',
        'confirmed_at',
        'pending_slot',
    ];

    protected function casts(): array
    {
        return [
            'status' => ScreenClaimStatus::class,
            'issued_token' => 'encrypted',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function scopeActivePending(Builder $query): Builder
    {
        return $query
            ->where('status', ScreenClaimStatus::Pending->value)
            ->where('expires_at', '>', now());
    }
}
