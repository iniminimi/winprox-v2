<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Zipbestand met een afgesloten jaar of met foto's die 6 maanden oud zijn.
 * Privé-disk. Download alleen via Abonnement.
 */
class TenantTimeArchive extends Model
{
    use BelongsToTenant;

    public const DISK = 'local';

    public const KIND_YEAR = 'year';

    public const KIND_PHOTOS = 'photos';

    public const DIRECTORY = 'time-archives';

    protected $fillable = [
        'tenant_id',
        'kind',
        'period_key',
        'path',
        'bytes',
        'available_until',
    ];

    protected $casts = [
        'bytes' => 'integer',
        'available_until' => 'datetime',
    ];

    public function storagePath(): string
    {
        return self::DIRECTORY.'/'.$this->tenant_id.'/'.($this->kind === self::KIND_YEAR
            ? 'jaar-'.$this->period_key.'.zip'
            : 'fotos-'.$this->period_key.'.zip');
    }
}
