<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eenmalig ticket waarmee de telefoon de computer aanmeldt.
 */
class BackofficeLoginChallenge extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AUTHENTICATED = 'authenticated';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CONSUMED = 'consumed';

    protected $fillable = [
        'token_hash',
        'confirmation_code',
        'session_id',
        'user_id',
        'tenant_id',
        'computer_label',
        'status',
        'expires_at',
        'assertion_options',
        'registration_options',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function findByPlainToken(string $token): ?self
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return static::query()->where('token_hash', hash('sha256', $token))->first();
    }

    public function isOpen(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isFuture()
            && $this->status !== self::STATUS_CONSUMED;
    }
}
