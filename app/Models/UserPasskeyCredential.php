<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WebAuthn-passkey van een beheerder of medewerker. Eén gsm per gebruiker.
 */
class UserPasskeyCredential extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'credential_id',
        'credential_public_key',
        'user_handle',
        'transports',
        'aaguid',
        'backup_eligible',
        'backup_status',
        'sign_count',
        'device_label',
        'last_used_at',
    ];

    protected $casts = [
        'transports' => 'array',
        'backup_eligible' => 'boolean',
        'backup_status' => 'boolean',
        'sign_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
