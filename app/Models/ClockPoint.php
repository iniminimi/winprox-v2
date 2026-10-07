<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Portal\TimePortalData;
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
        'homescreen_shortcut',
        'sort_order',
        'display_id',
        'display_secret',
        'display_token_hash',
        'display_token_prefix',
        'display_pairing_code',
        'display_pairing_expires_at',
        'display_device_hint',
        'display_paired_at',
        'display_last_seen_at',
        'display_on_from',
        'display_on_until',
        'album1_from',
        'album1_until',
        'album2_from',
        'album2_until',
        'album_mode',
        'album_days',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'homescreen_shortcut' => 'boolean',
        'qr_renewed_at' => 'datetime',
        'qr_renewal_recommended_at' => 'datetime',
        'display_secret' => 'encrypted',
        'display_pairing_expires_at' => 'datetime',
        'display_paired_at' => 'datetime',
        'display_last_seen_at' => 'datetime',
        'album_days' => 'integer',
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

    public function displayClaims(): HasMany
    {
        return $this->hasMany(ClockDisplayClaim::class);
    }

    public function displayImages(): HasMany
    {
        return $this->hasMany(ClockDisplayImage::class);
    }

    public function hasLinkedDisplay(): bool
    {
        return $this->display_id !== null && $this->display_secret !== null;
    }

    public function matchesDisplayToken(?string $plain): bool
    {
        if (! is_string($plain) || $plain === '' || $this->display_token_hash === null) {
            return false;
        }

        return hash_equals($this->display_token_hash, hash('sha256', $plain));
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

    /** Shorter path for outbound mail (same portal as portalUrl). */
    public function emailPortalUrl(): string
    {
        return route('public.time-portal.cp', $this->qr_token);
    }

    /** Plek op urenstaat: locatie, geen generieke naam zoals “Aanmelden”. */
    public function attendancePlaceLabel(): string
    {
        $locationName = trim($this->location?->localizedName() ?? '');
        $pointName = trim((string) $this->name);
        $generic = TimePortalData::isGenericClockPointName($pointName);

        if ($generic) {
            return $locationName !== ''
                ? $locationName
                : __('time.shifts.clock_point_fallback');
        }

        if ($locationName !== '' && strcasecmp($locationName, $pointName) !== 0) {
            return $locationName.' · '.$pointName;
        }

        if ($pointName !== '') {
            return $pointName;
        }

        return $locationName !== ''
            ? $locationName
            : __('time.shifts.clock_point_fallback');
    }
}
