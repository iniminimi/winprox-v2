<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IotRuleOperator;
use App\Enums\TaskPriority;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IotRule extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'iot_sensor_id',
        'name',
        'operator',
        'threshold',
        'internal_team_id',
        'priority',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'operator' => IotRuleOperator::class,
            'threshold' => 'decimal:4',
            'priority' => TaskPriority::class,
            'is_active' => 'boolean',
        ];
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(IotSensor::class, 'iot_sensor_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(InternalTeam::class, 'internal_team_id');
    }

    /**
     * Null value matches (binary alarm sensors without a numeric reading).
     */
    public function matchesValue(?float $value): bool
    {
        if ($value === null) {
            return true;
        }

        return $this->operator->matches($value, (float) $this->threshold);
    }
}
