<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto in het fotoalbum van een unit. Bestand op de privé-disk (`local`);
 * nooit via een publieke URL (docs/FEATURES.md §5h).
 */
class UnitAlbumPhoto extends Model
{
    use BelongsToTenant, HasFactory;

    public const DISK = 'local';

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'worker_id',
        'path',
        'taken_on',
    ];

    protected $casts = [
        'taken_on' => 'date',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
