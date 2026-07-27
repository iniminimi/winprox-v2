<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IotSensorType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IotSensor extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'iot_gateway_id',
        'external_id',
        'name',
        'sensor_type',
        'location_id',
        'unit_id',
        'esg_indicator_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sensor_type' => IotSensorType::class,
            'is_active' => 'boolean',
        ];
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(IotGateway::class, 'iot_gateway_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function esgIndicator(): BelongsTo
    {
        return $this->belongsTo(EsgIndicator::class, 'esg_indicator_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(IotRule::class, 'iot_sensor_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(IotEvent::class, 'iot_sensor_id');
    }
}
