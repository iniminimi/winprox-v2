<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HardwareDeviceCondition;
use App\Enums\HardwareDeviceStatus;
use App\Enums\HardwareDeviceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HardwareDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand',
        'type',
        'model',
        'sku',
        'serial_number',
        'mac',
        'tenant_id',
        'clock_point_id',
        'status',
        'condition',
        'sn_scanned_at',
        'mac_set_at',
        'assigned_to_tenant_at',
        'installed_at',
        'last_seen_at',
        'returned_at',
        'retired_at',
        'purchase_ref',
        'purchased_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => HardwareDeviceType::class,
            'status' => HardwareDeviceStatus::class,
            'condition' => HardwareDeviceCondition::class,
            'sn_scanned_at' => 'datetime',
            'mac_set_at' => 'datetime',
            'assigned_to_tenant_at' => 'datetime',
            'installed_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'returned_at' => 'datetime',
            'retired_at' => 'datetime',
            'purchased_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function clockPoint(): BelongsTo
    {
        return $this->belongsTo(ClockPoint::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(HardwareDeviceEvent::class);
    }
}
