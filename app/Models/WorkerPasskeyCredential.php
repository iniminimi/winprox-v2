<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WebAuthn/passkey van een uitvoerder voor de time-portaal verificatiestap.
 * credential_id (base64url) is bepalend — niet het toestel, want passkeys
 * kunnen via iCloud/Google naar een andere eigen gsm synchroniseren.
 */
class WorkerPasskeyCredential extends Model
{
    protected $fillable = [
        'tenant_id',
        'worker_id',
        'credential_id',
        'credential_public_key',
        'user_handle',
        'transports',
        'aaguid',
        'backup_eligible',
        'backup_status',
        'sign_count',
        'last_used_at',
    ];

    protected $casts = [
        'transports' => 'array',
        'backup_eligible' => 'boolean',
        'backup_status' => 'boolean',
        'sign_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
