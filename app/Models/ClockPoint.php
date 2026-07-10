<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClockPoint extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'name',
        'qr_token',
        'qr_renewed_at',
        'qr_renewal_recommended_at',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'qr_renewed_at' => 'datetime',
        'qr_renewal_recommended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClockPoint $clockPoint) {
            if (empty($clockPoint->qr_token)) {
                $clockPoint->qr_token = Str::lower(Str::random(40));
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function clockInShifts(): HasMany
    {
        return $this->hasMany(WorkShift::class, 'clock_in_clock_point_id');
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(ClockPointQrToken::class);
    }

    public function isRenewalRecommended(): bool
    {
        return $this->qr_renewal_recommended_at !== null
            && $this->qr_renewal_recommended_at->lessThanOrEqualTo(now());
    }

    public function portalUrl(): string
    {
        return route('public.time-portal', $this->qr_token);
    }
}
