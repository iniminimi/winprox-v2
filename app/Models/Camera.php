<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Camera extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'forwarding_enabled',
        'pairing_code',
        'pairing_expires_at',
        'device_hint',
        'paired_at',
        'last_seen_at',
        'token_hash',
        'token_prefix',
    ];

    protected function casts(): array
    {
        return [
            'forwarding_enabled' => 'boolean',
            'pairing_expires_at' => 'datetime',
            'paired_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(CameraClaim::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CameraPhoto::class);
    }

    public function isLinked(): bool
    {
        return $this->paired_at !== null && $this->token_hash !== null;
    }

    public function hasOpenCode(): bool
    {
        return $this->pairing_code !== null && $this->pairing_expires_at?->isFuture();
    }

    /**
     * @return array{plain: string, hash: string, prefix: string}
     */
    public static function issueToken(): array
    {
        $plain = 'wpcam_'.Str::lower(Str::random(40));

        return [
            'plain' => $plain,
            'hash' => hash('sha256', $plain),
            'prefix' => substr($plain, 0, 16),
        ];
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function matchesToken(string $plainToken): bool
    {
        return $this->token_hash !== null
            && hash_equals($this->token_hash, self::hashToken($plainToken));
    }
}
