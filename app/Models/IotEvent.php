<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IotEventKind;
use App\Enums\IotEventStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IotEvent extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'iot_gateway_id',
        'iot_sensor_id',
        'iot_rule_id',
        'kind',
        'external_sensor_id',
        'value',
        'status',
        'idempotency_key',
        'issue_id',
        'esg_measurement_id',
        'raw_payload',
        'occurred_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => IotEventKind::class,
            'status' => IotEventStatus::class,
            'value' => 'decimal:4',
            'raw_payload' => 'array',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(IotGateway::class, 'iot_gateway_id');
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(IotSensor::class, 'iot_sensor_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(IotRule::class, 'iot_rule_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function esgMeasurement(): BelongsTo
    {
        return $this->belongsTo(EsgMeasurement::class, 'esg_measurement_id');
    }
}
