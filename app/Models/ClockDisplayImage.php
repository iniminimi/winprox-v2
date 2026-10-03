<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Eén foto uit het slideshow-album van een klokscherm.
 * `clock_point_id = null` = globaal: zichtbaar op álle klokschermen van de tenant.
 * Bestand is client-side al vierkant (1:1) en ~480px gecomprimeerd — geen
 * server-resize (WINPROX_RULES.md §7).
 */
class ClockDisplayImage extends Model
{
    use BelongsToTenant, HasFactory;

    public const MAX_PER_SCOPE = 12;

    protected $fillable = [
        'tenant_id',
        'clock_point_id',
        'path',
        'sort_order',
    ];

    public function clockPoint(): BelongsTo
    {
        return $this->belongsTo(ClockPoint::class);
    }

    /** Beelden zichtbaar op dit punt: eigen + globale (NULL) — expliciete
     *  tenant-filter want de global scope staat uit zonder session-context
     *  (device-API). */
    public static function queryForPoint(ClockPoint $point)
    {
        return static::query()
            ->where('tenant_id', $point->tenant_id)
            ->where(fn ($q) => $q
                ->where('clock_point_id', $point->id)
                ->orWhereNull('clock_point_id'))
            ->orderByRaw('clock_point_id IS NULL')   // eerst eigen, dan globaal
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function publicUrl(): string
    {
        return asset(Storage::url($this->path));
    }
}
