<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScreenRole;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Screen extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'clock_point_id',
        'role',
        'has_sense',
        'display_id',
        'display_secret',
        'display_token_hash',
        'display_token_prefix',
        'qr_window_seconds',
        'display_on_from',
        'display_on_until',
        'display_sound_enabled',
        'album_mode',
        'album1_from',
        'album1_until',
        'album2_from',
        'album2_until',
        'album_days',
        'pairing_code',
        'pairing_expires_at',
        'device_hint',
        'paired_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => ScreenRole::class,
            'has_sense' => 'boolean',
            'display_secret' => 'encrypted',
            'display_sound_enabled' => 'boolean',
            'album_days' => 'integer',
            'qr_window_seconds' => 'integer',
            'pairing_expires_at' => 'datetime',
            'paired_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function clockPoint(): BelongsTo
    {
        return $this->belongsTo(ClockPoint::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(ScreenClaim::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ClockDisplayImage::class);
    }

    public function sensePoint(): HasOne
    {
        return $this->hasOne(SensePoint::class);
    }

    public function isLinked(): bool
    {
        return $this->paired_at !== null && $this->device_hint !== null;
    }

    public function hasOpenCode(): bool
    {
        return $this->pairing_code !== null && $this->pairing_expires_at?->isFuture();
    }

    public function matchesDisplayToken(string $plain): bool
    {
        if ($plain === '' || $this->display_token_hash === null) {
            return false;
        }

        return hash_equals($this->display_token_hash, hash('sha256', $plain));
    }
}
