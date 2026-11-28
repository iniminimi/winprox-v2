<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenseReading extends Model
{
    use BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'sense_point_id',
        'temperature',
        'humidity',
        'co2',
        'voc',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'humidity' => 'float',
            'co2' => 'integer',
            'voc' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(SensePoint::class, 'sense_point_id');
    }

    /**
     * @param  Builder<SenseReading>  $query
     * @return Builder<SenseReading>
     */
    public function scopeOutsideBands(Builder $query, SensePoint $point): Builder
    {
        return $query->where(function (Builder $inner) use ($point): void {
            $inner->where('temperature', '<', $point->temperature_low)
                ->orWhere('temperature', '>', $point->temperature_high)
                ->orWhere('humidity', '<', $point->humidity_low)
                ->orWhere('humidity', '>', $point->humidity_high)
                ->orWhere('co2', '>', $point->co2_high)
                ->orWhere('voc', '>', $point->voc_high);
        });
    }
}
