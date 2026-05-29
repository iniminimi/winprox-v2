<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Onthoudt een veldtoestel per worker (cookie ↔ device_token). Maakt het mogelijk
 * een gedeelde telefoon op de werkvloer aan een worker te koppelen (~1 jaar).
 */
class WorkerDevice extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'worker_id',
        'device_token',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public static function generateToken(): string
    {
        do {
            $token = Str::lower(Str::random(48));
        } while (static::withoutGlobalScope('tenant')->where('device_token', $token)->exists());

        return $token;
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
