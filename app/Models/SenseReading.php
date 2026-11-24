<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
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
}
