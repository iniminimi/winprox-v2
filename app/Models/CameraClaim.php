<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CameraClaimStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CameraClaim extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::saving(function (CameraClaim $claim): void {
            $claim->pending_slot = $claim->status === CameraClaimStatus::Pending
                ? $claim->camera_id
                : null;
        });
    }

    protected $fillable = [
        'tenant_id',
        'camera_id',
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
            'status' => CameraClaimStatus::class,
            'issued_token' => 'encrypted',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    public function scopeActivePending(Builder $query): Builder
    {
        return $query
            ->where('status', CameraClaimStatus::Pending->value)
            ->where('expires_at', '>', now());
    }
}
