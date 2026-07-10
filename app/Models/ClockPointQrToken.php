<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClockPointQrToken extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'clock_point_id',
        'qr_token',
        'grace_ends_at',
        'blocked_at',
    ];

    protected $casts = [
        'grace_ends_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public function clockPoint(): BelongsTo
    {
        return $this->belongsTo(ClockPoint::class);
    }

    public function isInGrace(): bool
    {
        return $this->blocked_at === null && $this->grace_ends_at->isFuture();
    }

    public function isBlocked(): bool
    {
        if ($this->blocked_at !== null) {
            return true;
        }

        return $this->grace_ends_at->isPast();
    }
}
