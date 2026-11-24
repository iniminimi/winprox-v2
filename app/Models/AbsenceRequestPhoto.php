<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto bij een afwezigheidsaanvraag. Bestand op de privé-disk, nooit een publieke URL.
 */
class AbsenceRequestPhoto extends Model
{
    use BelongsToTenant;

    public const DISK = 'local';

    protected $fillable = [
        'tenant_id',
        'absence_request_id',
        'path',
    ];

    public function absenceRequest(): BelongsTo
    {
        return $this->belongsTo(AbsenceRequest::class);
    }
}
